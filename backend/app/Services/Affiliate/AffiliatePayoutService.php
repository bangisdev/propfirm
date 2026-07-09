<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateCommission;
use App\Models\PayoutBankAccount;
use App\Models\User;
use App\Services\Payments\PaystackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AffiliatePayoutService
{
    public function __construct(private readonly PaystackService $paystack) {}

    /**
     * Aggregates ALL of an affiliate's pending commissions into one transfer
     * (rather than one transfer per referred order) — fewer, larger transfers
     * means less transfer-fee overhead and a simpler bank statement for the
     * affiliate to reconcile against.
     */
    public function payoutPending(User $affiliate): array
    {
        $pending = AffiliateCommission::where('affiliate_user_id', $affiliate->id)
            ->where('status', 'pending')
            ->get();

        if ($pending->isEmpty()) {
            throw ValidationException::withMessages(['affiliate' => 'No pending commissions to pay out.']);
        }

        $bankAccount = PayoutBankAccount::where('user_id', $affiliate->id)->where('is_default', true)->first();
        if (! $bankAccount) {
            throw ValidationException::withMessages(['affiliate' => 'This affiliate has no bank account on file.']);
        }

        $total = round((float) $pending->sum('commission_amount'), 2);
        $currency = $pending->first()->currency;

        if (! $bankAccount->paystack_recipient_code) {
            $recipient = $this->paystack->createTransferRecipient([
                'account_name' => $bankAccount->account_name,
                'account_number' => $bankAccount->account_number,
                'bank_code' => $bankAccount->bank_code,
                'currency' => $bankAccount->currency,
            ]);
            $bankAccount->update(['paystack_recipient_code' => $recipient['recipient_code']]);
        }

        $reference = 'AF-'.strtoupper(Str::random(16));

        $transfer = $this->paystack->initiateTransfer([
            'amount' => $total,
            'recipient_code' => $bankAccount->paystack_recipient_code,
            'reason' => 'Affiliate commission payout',
            'reference' => $reference,
        ]);

        DB::transaction(function () use ($pending, $transfer) {
            AffiliateCommission::whereIn('id', $pending->pluck('id'))->update([
                'status' => 'processing',
                'paystack_transfer_code' => $transfer['transfer_code'],
            ]);
        });

        return [
            'total_amount' => $total,
            'currency' => $currency,
            'commission_count' => $pending->count(),
            'paystack_transfer_code' => $transfer['transfer_code'],
        ];
    }
}
