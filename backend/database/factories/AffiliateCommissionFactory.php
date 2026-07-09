<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AffiliateCommission>
 */
class AffiliateCommissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'affiliate_user_id' => User::factory(),
            'referred_user_id' => User::factory(),
            'order_id' => Order::factory(),
            'order_amount' => 200,
            'commission_pct' => 10,
            'commission_amount' => 20,
            'currency' => 'USD',
            'status' => 'pending',
        ];
    }
}
