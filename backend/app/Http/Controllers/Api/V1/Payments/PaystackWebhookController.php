<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\PaymentWebhookEvent;
use App\Notifications\Payouts\PayoutPaidNotification;
use App\Services\Payments\PaymentFulfillmentService;
use App\Services\Payments\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private readonly PaystackService $paystack,
        private readonly PaymentFulfillmentService $fulfillment,
    ) {}

    /**
     * POST /api/v1/webhooks/paystack
     *
     * Security notes:
     * - Signature is verified against the RAW request body (not the parsed/re-encoded
     *   JSON), since re-encoding can change byte-for-byte content and break the HMAC.
     * - We always return 200 once the event is durably stored, even if fulfillment
     *   fails downstream (fulfillment is retried via the queued provisioning job and
     *   safe reconciliation on order polling) — returning non-2xx here just causes
     *   Paystack to retry the same webhook, which our event-id idempotency already
     *   protects against, so there's no benefit to holding the connection open.
     */
    public function handle(Request $request): Response
    {
        $signature = $request->header('x-paystack-signature');
        $rawBody = $request->getContent();

        if (! $this->paystack->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('Rejected Paystack webhook with invalid signature', ['ip' => $request->ip()]);

            return response()->noContent(401);
        }

        $payload = $request->json()->all();
        $eventId = $payload['data']['id'] ?? $payload['data']['reference'] ?? null;

        if (! $eventId) {
            return response()->noContent(400);
        }

        $event = PaymentWebhookEvent::firstOrCreate(
            ['provider' => 'paystack', 'event_id' => (string) $eventId],
            ['event_type' => $payload['event'] ?? 'unknown', 'payload' => $payload],
        );

        if ($event->processed_at) {
            return response()->noContent(200);
        }

        if (($payload['event'] ?? null) === 'charge.success') {
            $reference = $payload['data']['reference'] ?? null;
            if ($reference) {
                try {
                    $this->fulfillment->verifyAndFulfill($reference);
                } catch (Throwable $e) {
                    Log::error('Webhook fulfillment error', ['reference' => $reference, 'error' => $e->getMessage()]);
                }
            }
        }

        if (in_array($payload['event'] ?? null, ['transfer.success', 'transfer.failed', 'transfer.reversed'], true)) {
            $this->handleTransferEvent($payload);
        }

        $event->update(['processed_at' => now()]);

        return response()->noContent(200);
    }

    private function handleTransferEvent(array $payload): void
    {
        $transferCode = $payload['data']['transfer_code'] ?? null;
        if (! $transferCode) {
            return;
        }

        $payout = PayoutRequest::where('paystack_transfer_code', $transferCode)->first();
        if (! $payout || $payout->status !== 'processing') {
            return; // already handled, or not a payout transfer (e.g. an unrelated Paystack transfer)
        }

        if ($payload['event'] === 'transfer.success') {
            $payout->update(['status' => 'paid', 'paid_at' => now()]);

            // Roll the payout baseline forward so the NEXT payout only pays out
            // profit earned after this one, and reset the eligibility cooldown.
            $account = $payout->tradingAccount;
            $account->update([
                'payout_baseline_balance' => $account->current_balance,
                'next_payout_eligible_at' => now()->addDays($account->challenge->payout_cycle_days),
            ]);

            $payout->user->notify(new PayoutPaidNotification($payout));
        } else {
            $payout->update([
                'status' => 'failed',
                'admin_notes' => 'Paystack reported: '.($payload['event']),
            ]);
        }
    }
}
