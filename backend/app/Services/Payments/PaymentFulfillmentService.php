<?php

namespace App\Services\Payments;

use App\Jobs\ProvisionTradingAccountJob;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\Payments\OrderPaidNotification;
use App\Services\Affiliate\AffiliateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentFulfillmentService
{
    public function __construct(
        private readonly PaystackService $paystack,
        private readonly AffiliateService $affiliate,
    ) {}

    /**
     * Verifies a transaction reference against Paystack directly (never trusting
     * the raw webhook/redirect payload alone) and fulfills the order if it succeeded.
     * Safe to call multiple times for the same reference — fulfillment only runs once.
     */
    public function verifyAndFulfill(string $reference): Order
    {
        $order = Order::where('reference', $reference)->lockForUpdate()->firstOrFail();

        // Idempotency guard: webhook and browser-redirect can both land here.
        if ($order->status === 'paid') {
            return $order;
        }

        if ($order->isExpired()) {
            $order->update(['status' => 'expired']);

            return $order;
        }

        $transaction = $this->paystack->verifyTransaction($reference);

        if ($transaction['status'] !== 'success') {
            $order->update(['status' => 'failed']);
            Log::warning('Paystack transaction not successful', ['reference' => $reference, 'status' => $transaction['status']]);

            return $order;
        }

        // Defense in depth: the amount actually charged must match what we expect,
        // in case of a tampered client-side redirect amount or a stale reference reuse.
        $expectedAmount = (int) round((float) $order->total * 100);
        if ((int) $transaction['amount'] !== $expectedAmount) {
            Log::error('Paystack amount mismatch', [
                'reference' => $reference,
                'expected' => $expectedAmount,
                'received' => $transaction['amount'],
            ]);
            $order->update(['status' => 'failed']);

            return $order;
        }

        return DB::transaction(function () use ($order, $transaction) {
            Payment::create([
                'order_id' => $order->id,
                'provider' => 'paystack',
                'provider_reference' => $transaction['reference'],
                'amount' => $transaction['amount'] / 100,
                'currency' => $transaction['currency'],
                'status' => 'success',
                'channel' => $transaction['channel'] ?? null,
                'gateway_response' => $transaction,
                'paid_at' => now(),
            ]);

            $order->update(['status' => 'paid', 'paid_at' => now()]);

            $this->recordCouponRedemption($order);
            $this->affiliate->recordCommissionForOrder($order->fresh());

            ProvisionTradingAccountJob::dispatch($order->id);
            $order->user->notify(new OrderPaidNotification($order));

            return $order->fresh();
        });
    }

    /**
     * Used for 100%-off coupon checkouts that never touch Paystack at all.
     */
    public function markOrderPaidWithoutGateway(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order->update(['status' => 'paid', 'paid_at' => now()]);
            $this->recordCouponRedemption($order);
            $this->affiliate->recordCommissionForOrder($order->fresh());
            ProvisionTradingAccountJob::dispatch($order->id);
            $order->user->notify(new OrderPaidNotification($order));

            return $order->fresh();
        });
    }

    private function recordCouponRedemption(Order $order): void
    {
        if (! $order->coupon_id) {
            return;
        }

        // Row lock on the coupon prevents two concurrent checkouts from both
        // reading times_redeemed < max_redemptions and both squeezing past the limit.
        $coupon = $order->coupon()->lockForUpdate()->first();
        if (! $coupon) {
            return;
        }

        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
        ]);

        $coupon->increment('times_redeemed');
    }
}
