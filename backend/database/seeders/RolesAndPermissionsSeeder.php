<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Challenges & trading
            'challenges.view', 'challenges.purchase', 'challenges.manage',
            'accounts.view', 'accounts.manage',
            'trading-rules.manage',

            // Payments & payouts
            'payments.view', 'payments.manage',
            'withdrawals.request', 'withdrawals.approve',

            // KYC
            'kyc.submit', 'kyc.review',

            // Affiliate
            'affiliate.view', 'affiliate.manage',

            // Support
            'tickets.create', 'tickets.manage',

            // Admin / platform
            'users.manage', 'coupons.manage', 'reports.view', 'audit-logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        $trader = Role::firstOrCreate(['name' => 'trader', 'guard_name' => 'api']);
        $trader->syncPermissions([
            'challenges.view', 'challenges.purchase',
            'accounts.view',
            'payments.view', 'withdrawals.request',
            'kyc.submit',
            'affiliate.view',
            'tickets.create',
        ]);

        $affiliate = Role::firstOrCreate(['name' => 'affiliate', 'guard_name' => 'api']);
        $affiliate->syncPermissions(['affiliate.view', 'tickets.create']);

        $support = Role::firstOrCreate(['name' => 'support', 'guard_name' => 'api']);
        $support->syncPermissions(['tickets.manage', 'users.manage', 'kyc.review']);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->syncPermissions(Permission::all());
    }
}
