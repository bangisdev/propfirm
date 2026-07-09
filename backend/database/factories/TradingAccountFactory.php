<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TradingAccount>
 */
class TradingAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'challenge_id' => Challenge::factory(),
            'phase' => 'evaluation_1',
            'status' => 'provisioning',
            'starting_balance' => 10000,
            'current_balance' => 10000,
            'current_equity' => 10000,
            'highest_balance' => 10000,
            'day_start_balance' => 10000,
            'day_start_date' => now()->toDateString(),
            'phase_start_balance' => 10000,
            'trading_days_count' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active', 'mt5_login' => fake()->unique()->numberBetween(100000, 999999)]);
    }

    public function breached(): static
    {
        return $this->state(fn () => [
            'status' => 'breached',
            'breached_at' => now(),
            'breach_reason' => 'Maximum total drawdown limit exceeded.',
        ]);
    }

    public function funded(): static
    {
        return $this->state(fn () => ['phase' => 'funded', 'status' => 'funded']);
    }
}
