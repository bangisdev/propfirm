<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'type' => 'percentage',
            'value' => 10,
            'currency' => 'USD',
            'max_redemptions' => null,
            'times_redeemed' => 0,
            'max_redemptions_per_user' => 1,
            'applicable_challenge_ids' => null,
            'minimum_order_amount' => 0,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    public function percentageOff(float $pct): static
    {
        return $this->state(fn () => ['type' => 'percentage', 'value' => $pct]);
    }

    public function fixedOff(float $amount): static
    {
        return $this->state(fn () => ['type' => 'fixed', 'value' => $amount]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function maxedOut(): static
    {
        return $this->state(fn () => ['max_redemptions' => 1, 'times_redeemed' => 1]);
    }
}
