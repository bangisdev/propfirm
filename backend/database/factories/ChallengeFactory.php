<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Challenge>
 */
class ChallengeFactory extends Factory
{
    public function definition(): array
    {
        $size = fake()->randomElement([10000, 25000, 50000, 100000]);
        $name = 'Two-Step Evaluation — $'.number_format($size).' '.Str::random(4);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'phase_count' => 2,
            'account_size' => $size,
            'currency' => 'USD',
            'price' => fake()->randomFloat(2, 39, 699),
            'profit_target_phase1_pct' => 10.00,
            'profit_target_phase2_pct' => 5.00,
            'max_daily_drawdown_pct' => 5.00,
            'max_total_drawdown_pct' => 10.00,
            'min_trading_days' => 5,
            'profit_split_pct' => 80.00,
            'news_trading_restricted' => true,
            'weekend_holding_allowed' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
