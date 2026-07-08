<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasUuids;

    protected $fillable = [
        'code', 'type', 'value', 'currency',
        'max_redemptions', 'times_redeemed', 'max_redemptions_per_user',
        'applicable_challenge_ids', 'minimum_order_amount',
        'starts_at', 'expires_at', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'applicable_challenge_ids' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Pure validation — no side effects. Returns an error message or null if valid.
     * Called at checkout time; the actual redemption row is written inside the
     * order-creation DB transaction to avoid a race between check and use.
     */
    public function validationError(Challenge $challenge, string $userId): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is no longer active.';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'This coupon is not active yet.';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'This coupon has expired.';
        }
        if ($this->max_redemptions !== null && $this->times_redeemed >= $this->max_redemptions) {
            return 'This coupon has reached its usage limit.';
        }
        if ($this->applicable_challenge_ids && ! in_array($challenge->id, $this->applicable_challenge_ids, true)) {
            return 'This coupon is not valid for the selected challenge.';
        }
        if (bccomp((string) $challenge->price, (string) $this->minimum_order_amount, 2) < 0) {
            return "This coupon requires a minimum order of {$this->minimum_order_amount}.";
        }

        $userRedemptions = $this->redemptions()->where('user_id', $userId)->count();
        if ($userRedemptions >= $this->max_redemptions_per_user) {
            return 'You have already used this coupon the maximum number of times.';
        }

        return null;
    }

    public function calculateDiscount(float $subtotal): float
    {
        $discount = $this->type === 'percentage'
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        return round(min($discount, $subtotal), 2);
    }
}
