<?php

use App\Models\SupportTicket;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

function authHeadersForTicket(User $user): array
{
    $token = auth('api')->login($user);

    return ['Authorization' => "Bearer {$token}"];
}

it('creates a ticket with an initial message', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(authHeadersForTicket($user))->postJson('/api/v1/support-tickets', [
        'subject' => 'Cannot log into MT5',
        'category' => 'trading',
        'message' => 'My MT5 login keeps failing.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonCount(1, 'data.messages');
});

it('rejects a reply on a closed ticket', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->create(['user_id' => $user->id, 'status' => 'closed']);

    $response = $this->withHeaders(authHeadersForTicket($user))
        ->postJson("/api/v1/support-tickets/{$ticket->id}/reply", ['message' => 'Still an issue']);

    $response->assertStatus(422);
});

it('reopens an in_progress ticket when the trader replies', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);

    $response = $this->withHeaders(authHeadersForTicket($user))
        ->postJson("/api/v1/support-tickets/{$ticket->id}/reply", ['message' => 'Following up']);

    $response->assertOk()->assertJsonPath('data.status', 'open');
});

it('prevents a trader from viewing another traders ticket', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ticket = SupportTicket::factory()->create(['user_id' => $owner->id]);

    $response = $this->withHeaders(authHeadersForTicket($intruder))->getJson("/api/v1/support-tickets/{$ticket->id}");

    $response->assertStatus(403);
});

it('hides internal staff notes from the trader-facing ticket view', function () {
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->create(['user_id' => $user->id]);
    $ticket->messages()->create(['user_id' => $user->id, 'message' => 'Public message', 'is_internal_note' => false]);
    $ticket->messages()->create(['user_id' => $user->id, 'message' => 'Secret staff note', 'is_internal_note' => true]);

    $response = $this->withHeaders(authHeadersForTicket($user))->getJson("/api/v1/support-tickets/{$ticket->id}");

    $messages = collect($response->json('data.messages'))->pluck('message');
    expect($messages)->toContain('Public message');
    expect($messages)->not->toContain('Secret staff note');
});

it('shows internal staff notes to a support agent', function () {
    $support = User::factory()->create();
    $support->assignRole('support');
    $trader = User::factory()->create();
    $ticket = SupportTicket::factory()->create(['user_id' => $trader->id]);
    $ticket->messages()->create(['user_id' => $trader->id, 'message' => 'Internal only', 'is_internal_note' => true]);

    $response = $this->withHeaders(authHeadersForTicket($support))
        ->getJson("/api/v1/admin/support-tickets/{$ticket->id}");

    $messages = collect($response->json('data.messages'))->pluck('message');
    expect($messages)->toContain('Internal only');
});

it('allows an admin to assign a ticket to a support agent', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $agent = User::factory()->create();
    $ticket = SupportTicket::factory()->create();

    $response = $this->withHeaders(authHeadersForTicket($admin))
        ->postJson("/api/v1/admin/support-tickets/{$ticket->id}/assign", ['assigned_to' => $agent->id]);

    $response->assertOk()->assertJsonPath('data.status', 'in_progress');
});

it('rejects a trader from accessing admin ticket endpoints', function () {
    $trader = User::factory()->create();
    $trader->assignRole('trader');
    $ticket = SupportTicket::factory()->create();

    $response = $this->withHeaders(authHeadersForTicket($trader))->getJson('/api/v1/admin/support-tickets');

    $response->assertStatus(403);
});
