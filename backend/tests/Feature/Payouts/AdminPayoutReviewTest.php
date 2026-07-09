<?php

use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

function authHeadersFor(User $user): array
{
    $token = auth('api')->login($user);

    return ['Authorization' => "Bearer {$token}"];
}

it('allows an admin to approve a pending payout and dispatches processing', function () {
    Queue::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $payout = PayoutRequest::factory()->create(['status' => 'pending']);

    $response = $this->withHeaders(authHeadersFor($admin))
        ->postJson("/api/v1/admin/payout-requests/{$payout->id}/approve", ['notes' => 'Looks good']);

    $response->assertOk()->assertJsonPath('data.status', 'approved');
    Queue::assertPushed(\App\Jobs\ProcessPayoutJob::class);
});

it('allows an admin to reject a pending payout with a reason', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $payout = PayoutRequest::factory()->create(['status' => 'pending']);

    $response = $this->withHeaders(authHeadersFor($admin))
        ->postJson("/api/v1/admin/payout-requests/{$payout->id}/reject", ['reason' => 'Suspicious activity']);

    $response->assertOk()->assertJsonPath('data.status', 'rejected');
});

it('requires a reason to reject a payout', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $payout = PayoutRequest::factory()->create(['status' => 'pending']);

    $response = $this->withHeaders(authHeadersFor($admin))
        ->postJson("/api/v1/admin/payout-requests/{$payout->id}/reject", []);

    $response->assertStatus(422)->assertJsonValidationErrors(['reason']);
});

it('rejects a trader (non-admin) from accessing the payout review endpoint', function () {
    $trader = User::factory()->create();
    $trader->assignRole('trader');
    $payout = PayoutRequest::factory()->create(['status' => 'pending']);

    $response = $this->withHeaders(authHeadersFor($trader))
        ->postJson("/api/v1/admin/payout-requests/{$payout->id}/approve", []);

    $response->assertStatus(403);
});

it('does not allow approving a payout that is not pending', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $payout = PayoutRequest::factory()->create(['status' => 'paid']);

    $response = $this->withHeaders(authHeadersFor($admin))
        ->postJson("/api/v1/admin/payout-requests/{$payout->id}/approve", []);

    $response->assertStatus(422);
});
