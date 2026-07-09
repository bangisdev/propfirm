<?php

namespace App\Services\TradingRules;

use App\Models\TradingAccount;
use App\Notifications\TradingRules\AccountBreachedNotification;
use App\Notifications\TradingRules\PhaseAdvancedNotification;
use App\Services\MT5\MT5BridgeClientInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TradingAccountSyncService
{
    public function __construct(
        private readonly MT5BridgeClientInterface $bridge,
        private readonly TradingRuleEngine $engine,
    ) {}

    /**
     * Syncs a single trading account: pulls fresh MT5 state, updates trading-day
     * tracking, runs the rule engine, and applies whatever outcome results.
     * Safe to call repeatedly — each call is a fresh evaluation against current state.
     */
    public function sync(TradingAccount $account): RuleEvaluationOutcome
    {
        if (! $account->isProvisioned() || ! in_array($account->status, ['active'], true)) {
            return RuleEvaluationOutcome::NoAction;
        }

        try {
            $state = $this->bridge->fetchAccountState($account->mt5_login);
        } catch (Throwable $e) {
            Log::error('MT5 state fetch failed during sync', [
                'trading_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return RuleEvaluationOutcome::NoAction;
        }

        return DB::transaction(function () use ($account, $state) {
            $this->applyFreshState($account, $state);

            $snapshot = $account->toSnapshot();
            $outcome = $this->engine->evaluate($snapshot);

            match (true) {
                $outcome->isBreach() => $this->applyBreach($account, $outcome),
                $outcome->isAdvancement() => $this->applyAdvancement($account, $outcome),
                default => null,
            };

            $account->update(['last_synced_at' => now()]);

            return $outcome;
        });
    }

    /**
     * @param array{balance: float, equity: float, open_positions: int, last_trade_at: ?string} $state
     */
    private function applyFreshState(TradingAccount $account, array $state): void
    {
        $today = now()->toDateString();

        // Daily baseline rolls over automatically here too (in addition to the
        // scheduled midnight reset command) so a sync that runs right after
        // midnight, before the reset command fires, doesn't use yesterday's floor.
        if ($account->day_start_date === null || $account->day_start_date->toDateString() !== $today) {
            $account->day_start_balance = $account->current_balance ?? $account->starting_balance;
            $account->day_start_date = $today;
        }

        $account->current_balance = $state['balance'];
        $account->current_equity = $state['equity'];
        $account->highest_balance = max((float) $account->highest_balance, $state['balance']);

        if (! empty($state['last_trade_at'])) {
            $lastTradeDate = Carbon::parse($state['last_trade_at'])->toDateString();

            if ($account->first_trade_date === null) {
                $account->first_trade_date = $lastTradeDate;
            }

            if ($account->last_activity_date === null || $account->last_activity_date->toDateString() !== $lastTradeDate) {
                $account->trading_days_count += 1;
                $account->last_activity_date = $lastTradeDate;
            }
        }

        $account->save();
    }

    private function applyBreach(TradingAccount $account, RuleEvaluationOutcome $outcome): void
    {
        $reason = $outcome === RuleEvaluationOutcome::BreachedMaxDrawdown
            ? 'Maximum total drawdown limit exceeded.'
            : 'Maximum daily drawdown limit exceeded.';

        $account->update([
            'status' => 'breached',
            'breached_at' => now(),
            'breach_reason' => $reason,
        ]);

        try {
            $this->bridge->disableAccount($account->mt5_login);
        } catch (Throwable $e) {
            Log::error('Failed to disable MT5 account after breach', [
                'trading_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }

        $account->user->notify(new AccountBreachedNotification($account, $reason));
    }

    private function applyAdvancement(TradingAccount $account, RuleEvaluationOutcome $outcome): void
    {
        $newPhase = $outcome === RuleEvaluationOutcome::Funded ? 'funded' : 'evaluation_2';

        $updates = [
            'phase' => $newPhase,
            'status' => $newPhase === 'funded' ? 'funded' : 'active',
            'phase_start_balance' => $account->current_balance,
            'trading_days_count' => 0, // each phase re-earns its own minimum trading days
        ];

        if ($newPhase === 'funded') {
            // Payout eligibility starts counting down from the moment funding begins.
            $updates['payout_baseline_balance'] = $account->current_balance;
            $updates['next_payout_eligible_at'] = now()->addDays($account->challenge->payout_cycle_days);
        }

        $account->update($updates);

        $account->user->notify(new PhaseAdvancedNotification($account, $newPhase));
    }
}
