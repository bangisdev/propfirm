<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Auth\WelcomeNotification;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

it('registers a new user and returns an access token plus refresh cookie', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Trader',
        'email' => 'jane@example.com',
        'password' => 'StrongPass1!',
        'password_confirmation' => 'StrongPass1!',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['user' => ['id', 'email', 'role'], 'access_token', 'expires_in']])
        ->assertCookie('refresh_token');

    expect($response->json('data.user.role'))->toBe('trader');

    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    Notification::assertSentTo(User::where('email', 'jane@example.com')->first(), WelcomeNotification::class);
});

it('rejects registration with a weak password', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Trader',
        'email' => 'jane@example.com',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['password']);
});

it('rejects registration with a duplicate email', function () {
    User::factory()->create(['email' => 'dup@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Someone',
        'email' => 'dup@example.com',
        'password' => 'StrongPass1!',
        'password_confirmation' => 'StrongPass1!',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('applies a referral code linkage on registration', function () {
    $referrer = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Referred User',
        'email' => 'referred@example.com',
        'password' => 'StrongPass1!',
        'password_confirmation' => 'StrongPass1!',
        'referral_code' => $referrer->referral_code,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('users', [
        'email' => 'referred@example.com',
        'referred_by' => $referrer->id,
    ]);
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('CorrectHorse1!')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'CorrectHorse1!',
    ]);

    $response->assertOk()->assertCookie('refresh_token');
});

it('rejects login with invalid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('CorrectHorse1!')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'WrongPassword!',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('rejects login for a suspended account', function () {
    $user = User::factory()->suspended()->create(['password' => bcrypt('CorrectHorse1!')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'CorrectHorse1!',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('throttles login after too many failed attempts', function () {
    $user = User::factory()->create(['password' => bcrypt('CorrectHorse1!')]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong']);

    $response->assertStatus(422)->assertJsonFragment(['email' => ['Too many login attempts. Please try again in 60 seconds.']]);
});

it('returns the authenticated user profile via /me', function () {
    $user = User::factory()->create();
    $user->assignRole('trader');
    $token = auth('api')->login($user);

    $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me');

    $response->assertOk()->assertJsonPath('data.email', $user->email);
});

it('rejects /me without a token', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('rotates the refresh token and issues a new access token on refresh', function () {
    $user = User::factory()->create();

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $refreshCookie = $loginResponse->getCookie('refresh_token');

    $refreshResponse = $this->withCookie('refresh_token', $refreshCookie->getValue())
        ->postJson('/api/v1/auth/refresh');

    $refreshResponse->assertOk()->assertJsonStructure(['data' => ['access_token', 'expires_in']]);

    // old cookie is now revoked — reusing it must fail
    $reuseResponse = $this->withCookie('refresh_token', $refreshCookie->getValue())
        ->postJson('/api/v1/auth/refresh');

    $reuseResponse->assertStatus(401);
});

it('logs out and revokes the refresh token', function () {
    $user = User::factory()->create();
    $token = auth('api')->login($user);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);
    $refreshCookie = $loginResponse->getCookie('refresh_token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withCookie('refresh_token', $refreshCookie->getValue())
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    $this->withCookie('refresh_token', $refreshCookie->getValue())
        ->postJson('/api/v1/auth/refresh')
        ->assertStatus(401);
});
