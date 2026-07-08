<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(ChallengeSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@propfirm.io'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('ChangeMe123!'),
                'referral_code' => strtoupper(Str::random(8)),
                'email_verified_at' => now(),
                'kyc_status' => 'verified',
            ]
        );
        $admin->syncRoles(['admin']);

        if (app()->environment('local', 'testing')) {
            User::factory()
                ->count(20)
                ->create()
                ->each(fn (User $user) => $user->assignRole('trader'));
        }
    }
}
