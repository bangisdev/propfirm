<?php

namespace App\Services\Payouts;

use App\Jobs\ProcessPayoutJob;
use App\Models\PayoutBankAccount;
use App\Models\PayoutRequest;
use App\Models\TradingAccount;
use App\Models\User;
use App\Notifications\Payouts\PayoutApprovedNotification;
use App\Notifications\Payouts\PayoutRejectedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    public function __construct(private readonly PayoutCalculator $calculator) {}

    /**
     * Validates eligibility and creates a pending payout request.
     * Throws ValidationException with a user-facing message on any failure.
     */
    public function request(TradingAccount $account, PayoutBankAccount $bankAccount, float $amount): PayoutRequest
    {
        $this->assertEligible($account, $amount);

        $challenge = $account->challenge;
        $split = $this->calculator->split($amount, (float) $challenge->profit_split_pct);

        return DB::transaction(fn () => PayoutRequest::create([
            'trading_account_id' => $account->id,
            'user_id' => $account->user_id,
            'bank_account_id' => $bankAccount->id,
            'requested_amount' => $amount,
            'profit_split_pct' => $challenge->profit_split_pct,
            'trader_amount' => $split['trader_amount'],
            'firm_amount' => $split['firm_amount'],
            'currency' => $challenge->currency,
            'status' => 'pending',
        ]));
    }

    private function assertEligible(TradingAccount $account, float $amount): void
    {
        if ($account->status !== 'funded') {
            throw ValidationException::withMessages(['account' => 'Only funded accounts are eligible for payouts.']);
        }

        if ($account->payoutRequests()->whereIn('status', ['pending', 'approved', 'processing'])->exists()) {
            throw ValidationException::withMessages(['account' => 'You already have a payout request in progress for this account.']);
        }

        if ($account->next_payout_eligible_at && $account->next_payout_eligible_at->isFuture()) {
            $date = $account->next_payout_eligible_at->toFormattedDateString();
            throw ValidationException::withMessages(['account' => "You're not eligible for another payout until {$date}."]);
        }

        $challenge = $account->challenge;

        if ($amount < (float) $challenge->min_payout_amount) {
            throw ValidationException::withMessages([
                'amount' => "The minimum payout amount is {$challenge->min_payout_amount} {$challenge->currency}.",
            ]);
        }

        $available = $account->availableProfit();
        if ($amount > $available) {
            throw ValidationException::withMessages([
                'amount' => "You can withdraw at most {$available} {$challenge->currency} in realized profit.",
            ]);
        }
    }

    /**
     * Admin approves a pending request — snapshots the reviewer, then dispatches
     * the actual Paystack transfer asynchronously (never block the HTTP request
     * on an external payment API call).
     */
    public function approve(PayoutRequest $payout, User $admin, ?string $notes = null): PayoutRequest
    {
        if ($payout->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Only pending requests can be approved.']);
        }

        $payout->update([
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'admin_notes' => $notes,
        ]);

        $payout->user->notify(new PayoutApprovedNotification($payout));

        ProcessPayoutJob::dispatch($payout->id);

        return $payout->fresh();
    }

    public function reject(PayoutRequest $payout, User $admin, string $reason): PayoutRequest
    {
        if ($payout->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Only pending requests can be rejected.']);
        }

        $payout->update([
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'admin_notes' => $reason,
        ]);

        $payout->user->notify(new PayoutRejectedNotification($payout, $reason));

        return $payout->fresh();
    }
}
