<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PayoutBankAccount>
 */
class PayoutBankAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_name' => 'Test Bank',
            'bank_code' => '058',
            'account_number' => (string) fake()->numerify('##########'),
            'account_name' => fake()->name(),
            'currency' => 'NGN',
            'is_default' => true,
        ];
    }
}
