<?php

namespace App\Models;

use App\Services\TradingRules\AccountSnapshot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TradingAccount extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'order_id', 'challenge_id',
        'mt5_login', 'mt5_password_encrypted', 'mt5_server',
        'phase', 'status',
        'starting_balance', 'current_balance', 'current_equity', 'highest_balance',
        'day_start_balance', 'day_start_date', 'phase_start_balance',
        'payout_baseline_balance', 'next_payout_eligible_at',
        'first_trade_date', 'last_activity_date', 'trading_days_count',
        'provisioned_at', 'breached_at', 'breach_reason', 'last_synced_at',
    ];

    protected $hidden = ['mt5_password_encrypted'];

    protected function casts(): array
    {
        return [
            'starting_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'current_equity' => 'decimal:2',
            'highest_balance' => 'decimal:2',
            'day_start_balance' => 'decimal:2',
            'phase_start_balance' => 'decimal:2',
            'payout_baseline_balance' => 'decimal:2',
            'next_payout_eligible_at' => 'datetime',
            'day_start_date' => 'date',
            'first_trade_date' => 'date',
            'last_activity_date' => 'date',
            'provisioned_at' => 'datetime',
            'breached_at' => 'datetime',
            'last_synced_at' => 'datetime',
            // Encrypted cast: transparently encrypts on write, decrypts on read,
            // using APP_KEY — MT5 investor passwords must never sit in plaintext.
            'mt5_password_encrypted' => 'encrypted',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class);
    }

    /**
     * Realized profit available to withdraw: everything earned since the last
     * paid payout (or since becoming funded, if there hasn't been one yet).
     * Uses current_balance (realized/closed P&L), not equity, so open floating
     * profit can't be withdrawn before it's actually closed out.
     */
    public function availableProfit(): float
    {
        $baseline = (float) ($this->payout_baseline_balance ?? $this->starting_balance);

        return max(0.0, (float) ($this->current_balance ?? $this->starting_balance) - $baseline);
    }

    public function isProvisioned(): bool
    {
        return ! is_null($this->mt5_login);
    }

    /**
     * Builds the pure input DTO the rule engine operates on. Centralized here so
     * both the sync service (which applies outcomes) and API resources (which
     * show live progress in the dashboard) derive it identically.
     */
    public function toSnapshot(): AccountSnapshot
    {
        $challenge = $this->challenge;

        $profitTargetPct = match ($this->phase) {
            'evaluation_1' => (float) $challenge->profit_target_phase1_pct,
            'evaluation_2' => $challenge->profit_target_phase2_pct !== null
                ? (float) $challenge->profit_target_phase2_pct : null,
            default => null,
        };

        return new AccountSnapshot(
            phase: $this->phase,
            startingBalance: (float) $this->starting_balance,
            phaseStartBalance: (float) ($this->phase_start_balance ?? $this->starting_balance),
            dayStartBalance: (float) ($this->day_start_balance ?? $this->starting_balance),
            highestBalance: (float) $this->highest_balance,
            currentBalance: (float) ($this->current_balance ?? $this->starting_balance),
            currentEquity: (float) ($this->current_equity ?? $this->starting_balance),
            tradingDaysCount: $this->trading_days_count,
            minTradingDays: $challenge->min_trading_days,
            maxDailyDrawdownPct: (float) $challenge->max_daily_drawdown_pct,
            maxTotalDrawdownPct: (float) $challenge->max_total_drawdown_pct,
            profitTargetPct: $profitTargetPct,
            hasSecondPhase: $challenge->phase_count >= 2,
        );
    }
}
