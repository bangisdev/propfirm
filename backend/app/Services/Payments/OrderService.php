<?php

namespace App\Services\Payments;

use App\Models\Challenge;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Services\Coupons\CouponService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    private const CHECKOUT_TTL_MINUTES = 30;

    public function __construct(
        private readonly PaystackService $paystack,
        private readonly CouponService $coupons,
    ) {}

    /**
     * Creates a pending order for the given challenge (optionally with a coupon)
     * and returns [Order, paystackAuthorizationUrl].
     */
    public function checkout(User $user, Challenge $challenge, ?string $couponCode, string $callbackUrl): array
    {
        $coupon = null;
        $discount = 0.0;

        if ($couponCode) {
            $coupon = $this->coupons->validateForCheckout($couponCode, $challenge, $user->id);
            $discount = $coupon->calculateDiscount((float) $challenge->price);
        }

        $subtotal = (float) $challenge->price;
        $total = round($subtotal - $discount, 2);

        $order = DB::transaction(function () use ($user, $challenge, $coupon, $subtotal, $discount, $total) {
            return Order::create([
                'user_id' => $user->id,
                'challenge_id' => $challenge->id,
                'coupon_id' => $coupon?->id,
                'reference' => $this->generateReference(),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total' => $total,
                'currency' => $challenge->currency,
                'status' => 'pending',
                'expires_at' => now()->addMinutes(self::CHECKOUT_TTL_MINUTES),
            ]);
        });

        // Free challenges (100%-off coupon) skip the gateway entirely.
        if ($total <= 0) {
            app(PaymentFulfillmentService::class)->markOrderPaidWithoutGateway($order);

            return [$order->fresh(), null];
        }

        $paystackData = $this->paystack->initializeTransaction([
            'email' => $user->email,
            'amount' => $total,
            'currency' => $challenge->currency,
            'reference' => $order->reference,
            'callback_url' => $callbackUrl,
            'metadata' => ['order_id' => $order->id, 'user_id' => $user->id],
        ]);

        return [$order, $paystackData['authorization_url']];
    }

    private function generateReference(): string
    {
        do {
            $reference = 'PF-'.strtoupper(Str::random(16));
        } while (Order::where('reference', $reference)->exists());

        return $reference;
    }
}
