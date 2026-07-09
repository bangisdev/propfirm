<?php

namespace App\Jobs;

use App\Models\PayoutRequest;
use App\Services\Payments\PaystackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 300, 1800];

    public function __construct(private readonly string $payoutRequestId) {}

    public function handle(PaystackService $paystack): void
    {
        $payout = PayoutRequest::with('bankAccount')->find($this->payoutRequestId);

        if (! $payout || $payout->status !== 'approved') {
            Log::warning('Skipping payout processing: not found or not approved', ['payout_id' => $this->payoutRequestId]);

            return;
        }

        $bankAccount = $payout->bankAccount;

        if (! $bankAccount->paystack_recipient_code) {
            $recipient = $paystack->createTransferRecipient([
                'account_name' => $bankAccount->account_name,
                'account_number' => $bankAccount->account_number,
                'bank_code' => $bankAccount->bank_code,
                'currency' => $bankAccount->currency,
            ]);

            $bankAccount->update(['paystack_recipient_code' => $recipient['recipient_code']]);
        }

        $reference = 'PO-'.strtoupper(Str::random(16));

        $transfer = $paystack->initiateTransfer([
            'amount' => (float) $payout->trader_amount,
            'recipient_code' => $bankAccount->paystack_recipient_code,
            'reason' => 'PropFirm trader payout',
            'reference' => $reference,
        ]);

        // Paystack transfers are themselves asynchronous — this only confirms the
        // transfer was ACCEPTED for processing. Final success/failure arrives via
        // the transfer.success / transfer.failed webhook events, which flip
        // status to 'paid' or 'failed'.
        $payout->update([
            'status' => 'processing',
            'paystack_transfer_code' => $transfer['transfer_code'],
            'paystack_reference' => $reference,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Payout processing permanently failed', [
            'payout_id' => $this->payoutRequestId,
            'error' => $exception->getMessage(),
        ]);

        PayoutRequest::find($this->payoutRequestId)?->update([
            'status' => 'failed',
            'admin_notes' => 'Automatic transfer failed after retries: '.$exception->getMessage(),
        ]);
    }
}
