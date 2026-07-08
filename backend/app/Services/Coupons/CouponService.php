<?php

namespace App\Services\Coupons;

use App\Models\Challenge;
use App\Models\Coupon;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Validates a coupon code for a given user + challenge and returns the coupon,
     * or throws a ValidationException with a user-facing message.
     */
    public function validateForCheckout(string $code, Challenge $challenge, string $userId): Coupon
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon_code' => 'Invalid coupon code.']);
        }

        $error = $coupon->validationError($challenge, $userId);

        if ($error) {
            throw ValidationException::withMessages(['coupon_code' => $error]);
        }

        return $coupon;
    }
}
