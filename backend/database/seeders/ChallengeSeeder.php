<?php

namespace Database\Seeders;

use App\Models\Challenge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChallengeSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['size' => 5000, 'price' => 39],
            ['size' => 10000, 'price' => 69],
            ['size' => 25000, 'price' => 149],
            ['size' => 50000, 'price' => 249],
            ['size' => 100000, 'price' => 399],
            ['size' => 200000, 'price' => 699],
        ];

        foreach ($tiers as $i => $tier) {
            $name = 'Two-Step Evaluation — $'.number_format($tier['size']);

            Challenge::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'phase_count' => 2,
                    'account_size' => $tier['size'],
                    'currency' => 'USD',
                    'price' => $tier['price'],
                    'profit_target_phase1_pct' => 10.00,
                    'profit_target_phase2_pct' => 5.00,
                    'max_daily_drawdown_pct' => 5.00,
                    'max_total_drawdown_pct' => 10.00,
                    'min_trading_days' => 5,
                    'profit_split_pct' => 80.00,
                    'news_trading_restricted' => true,
                    'weekend_holding_allowed' => false,
                    'is_active' => true,
                    'sort_order' => $i,
                ]
            );
        }
    }
}
