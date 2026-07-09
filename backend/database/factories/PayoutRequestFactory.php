<?php

namespace Database\Factories;

use App\Models\PayoutBankAccount;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PayoutRequest>
 */
class PayoutRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trading_account_id' => TradingAccount::factory(),
            'user_id' => User::factory(),
            'bank_account_id' => PayoutBankAccount::factory(),
            'requested_amount' => 500,
            'profit_split_pct' => 80,
            'trader_amount' => 400,
            'firm_amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved', 'reviewed_at' => now()]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => 'processing', 'paystack_transfer_code' => 'TRF_'.fake()->uuid()]);
    }
}
