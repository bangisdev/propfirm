<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\KycSubmission>
 */
class KycSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'document_type' => 'passport',
            'document_front_path' => 'kyc/fake/front.jpg',
            'status' => 'pending',
        ];
    }
}
