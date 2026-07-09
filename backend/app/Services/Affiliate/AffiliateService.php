<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateCommission;
use App\Models\Order;

class AffiliateService
{
    /**
     * Records a commission for the referring affiliate when a referred trader's
     * order is paid. Returns null (no-op) if the trader wasn't referred, if
     * commission was already recorded for this order, or if this isn't their
     * qualifying order under the configured policy.
     *
     * Safe to call more than once for the same order — the unique constraint on
     * affiliate_commissions.order_id (and the existence check here) makes this
     * idempotent, since PaymentFulfillmentService can be invoked by both the
     * webhook and the redirect-verify path for the same paid order.
     */
    public function recordCommissionForOrder(Order $order): ?AffiliateCommission
    {
        $referredUser = $order->user;

        if (! $referredUser || ! $referredUser->referred_by) {
            return null;
        }

        if (AffiliateCommission::where('order_id', $order->id)->exists()) {
            return null;
        }

        if (config('affiliate.first_order_only') && $this->hasEarlierPaidOrder($order)) {
            return null;
        }

        $pct = (float) config('affiliate.commission_pct');
        $amount = round((float) $order->total * $pct / 100, 2);

        if ($amount <= 0) {
            return null;
        }

        return AffiliateCommission::create([
            'affiliate_user_id' => $referredUser->referred_by,
            'referred_user_id' => $referredUser->id,
            'order_id' => $order->id,
            'order_amount' => $order->total,
            'commission_pct' => $pct,
            'commission_amount' => $amount,
            'currency' => $order->currency,
            'status' => 'pending',
        ]);
    }

    private function hasEarlierPaidOrder(Order $order): bool
    {
        return Order::where('user_id', $order->user_id)
            ->where('status', 'paid')
            ->where('id', '!=', $order->id)
            ->exists();
    }
}
