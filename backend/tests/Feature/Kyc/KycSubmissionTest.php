<?php

use App\Models\KycSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

function authHeadersForKyc(User $user): array
{
    $token = auth('api')->login($user);

    return ['Authorization' => "Bearer {$token}"];
}

it('submits a KYC document and moves the user status to pending', function () {
    $user = User::factory()->create(['kyc_status' => 'unverified']);

    $response = $this->withHeaders(authHeadersForKyc($user))->post('/api/v1/kyc', [
        'document_type' => 'passport',
        'document_front' => UploadedFile::fake()->image('front.jpg'),
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'pending');
    expect($user->fresh()->kyc_status)->toBe('pending');

    $submission = KycSubmission::first();
    Storage::disk('local')->assertExists($submission->document_front_path);
});

it('rejects a second submission while one is already pending', function () {
    $user = User::factory()->create(['kyc_status' => 'pending']);
    KycSubmission::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

    $response = $this->withHeaders(authHeadersForKyc($user))->post('/api/v1/kyc', [
        'document_type' => 'passport',
        'document_front' => UploadedFile::fake()->image('front.jpg'),
    ]);

    $response->assertStatus(422);
});

it('rejects an invalid document type', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(authHeadersForKyc($user))->post('/api/v1/kyc', [
        'document_type' => 'not_a_real_type',
        'document_front' => UploadedFile::fake()->image('front.jpg'),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['document_type']);
});

it('rejects a document that is too large', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(authHeadersForKyc($user))->post('/api/v1/kyc', [
        'document_type' => 'passport',
        'document_front' => UploadedFile::fake()->create('front.pdf', 9000), // >8MB
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['document_front']);
});

it('allows an admin (support role) to approve a pending submission', function () {
    $support = User::factory()->create();
    $support->assignRole('support');
    $trader = User::factory()->create(['kyc_status' => 'pending']);
    $submission = KycSubmission::factory()->create(['user_id' => $trader->id, 'status' => 'pending']);

    $response = $this->withHeaders(authHeadersForKyc($support))
        ->postJson("/api/v1/admin/kyc-submissions/{$submission->id}/approve");

    $response->assertOk()->assertJsonPath('data.status', 'approved');
    expect($trader->fresh()->kyc_status)->toBe('verified');
});

it('allows an admin to reject a submission with a reason', function () {
    $support = User::factory()->create();
    $support->assignRole('support');
    $trader = User::factory()->create();
    $submission = KycSubmission::factory()->create(['user_id' => $trader->id, 'status' => 'pending']);

    $response = $this->withHeaders(authHeadersForKyc($support))
        ->postJson("/api/v1/admin/kyc-submissions/{$submission->id}/reject", ['reason' => 'Blurry document']);

    $response->assertOk()->assertJsonPath('data.status', 'rejected');
    expect($trader->fresh()->kyc_status)->toBe('rejected');
});

it('rejects a trader from accessing the admin KYC review endpoint', function () {
    $trader = User::factory()->create();
    $trader->assignRole('trader');
    $submission = KycSubmission::factory()->create();

    $response = $this->withHeaders(authHeadersForKyc($trader))
        ->postJson("/api/v1/admin/kyc-submissions/{$submission->id}/approve");

    $response->assertStatus(403);
});
