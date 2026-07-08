<?php

use App\Models\Challenge;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function authHeaders(User $user): array
{
    $token = auth('api')->login($user);

    return ['Authorization' => "Bearer {$token}"];
}

beforeEach(function () {
    Queue::fake(); // don't actually run MT5 provisioning during checkout tests
});

it('creates a pending order and returns a paystack authorization url', function () {
    $user = User::factory()->create();
    $challenge = Challenge::factory()->create(['price' => 249]);

    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc123',
                'access_code' => 'abc123',
                'reference' => 'PF-TESTREF',
            ],
        ], 200),
    ]);

    $response = $this->withHeaders(authHeaders($user))
        ->postJson('/api/v1/checkout', ['challenge_id' => $challenge->id]);

    $response->assertCreated()
        ->assertJsonPath('data.authorization_url', 'https://checkout.paystack.com/abc123')
        ->assertJsonPath('data.order.status', 'pending')
        ->assertJsonPath('data.order.total', 249.0);

    $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'status' => 'pending']);
});

it('rejects checkout for a nonexistent challenge', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(authHeaders($user))
        ->postJson('/api/v1/checkout', ['challenge_id' => (string) Str::uuid()]);

    $response->assertStatus(422)->assertJsonValidationErrors(['challenge_id']);
});

it('rejects checkout for an inactive challenge', function () {
    $user = User::factory()->create();
    $challenge = Challenge::factory()->inactive()->create();

    $response = $this->withHeaders(authHeaders($user))
        ->postJson('/api/v1/checkout', ['challenge_id' => $challenge->id]);

    $response->assertStatus(422)->assertJsonValidationErrors(['challenge_id']);
});

it('applies a valid percentage coupon and reduces the order total', function () {
    $user = User::factory()->create();
    $challenge = Challenge::factory()->create(['price' => 200]);
    $coupon = Coupon::factory()->percentageOff(20)->create();

    Http::fake(['api.paystack.co/*' => Http::response([
        'status' => true,
        'data' => ['authorization_url' => 'https://checkout.paystack.com/x', 'reference' => 'PF-X'],
    ], 200)]);

    $response = $this->withHeaders(authHeaders($user))->postJson('/api/v1/checkout', [
        'challenge_id' => $challenge->id,
        'coupon_code' => $coupon->code,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.order.discount_amount', 40.0)
        ->assertJsonPath('data.order.total', 160.0);
});

it('rejects an expired coupon with a clear validation message', function () {
    $user = User::factory()->create();
    $challenge = Challenge::factory()->create();
    $coupon = Coupon::factory()->expired()->create();

    $response = $this->withHeaders(authHeaders($user))->postJson('/api/v1/checkout', [
        'challenge_id' => $challenge->id,
        'coupon_code' => $coupon->code,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['coupon_code']);
});

it('rejects a coupon that has hit its redemption limit', function () {
    $user = User::factory()->create();
    $challenge = Challenge::factory()->create();
    $coupon = Coupon::factory()->maxedOut()->create();

    $response = $this->withHeaders(authHeaders($user))->postJson('/api/v1/checkout', [
        'challenge_id' => $challenge->id,
        'coupon_code' => $coupon->code,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['coupon_code']);
});

it('skips the payment gateway entirely for a 100%-off coupon and provisions immediately', function () {
    $user = User::factory()->create();
    $challenge = Challenge::factory()->create(['price' => 100]);
    $coupon = Coupon::factory()->percentageOff(100)->create();

    $response = $this->withHeaders(authHeaders($user))->postJson('/api/v1/checkout', [
        'challenge_id' => $challenge->id,
        'coupon_code' => $coupon->code,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.authorization_url', null)
        ->assertJsonPath('data.order.status', 'paid')
        ->assertJsonPath('data.order.total', 0.0);

    Queue::assertPushed(\App\Jobs\ProvisionTradingAccountJob::class);
});

it('lists the authenticated user order history', function () {
    $user = User::factory()->create();
    $challenge = Challenge::factory()->create();
    Order::factory()->count(3)->create(['user_id' => $user->id, 'challenge_id' => $challenge->id]);
    Order::factory()->create(); // someone else's order

    $response = $this->withHeaders(authHeaders($user))->getJson('/api/v1/orders');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(3);
});
