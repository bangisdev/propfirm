<?php

use App\Models\Order;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\Http;

function paystackSignature(string $body, string $secret): string
{
    return hash_hmac('sha512', $body, $secret);
}

beforeEach(function () {
    config(['services.paystack.secret_key' => 'test_secret_key']);
});

it('rejects a webhook with a missing signature header', function () {
    $response = $this->postJson('/api/v1/webhooks/paystack', ['event' => 'charge.success', 'data' => ['id' => '1']]);

    $response->assertStatus(401);
});

it('rejects a webhook with an invalid signature', function () {
    $body = json_encode(['event' => 'charge.success', 'data' => ['id' => '1', 'reference' => 'PF-X']]);

    $response = $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
        'HTTP_x-paystack-signature' => 'not-the-real-signature',
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $response->assertStatus(401);
});

it('accepts a webhook with a valid signature and fulfills the order', function () {
    $order = Order::factory()->create(['reference' => 'PF-VALIDTEST', 'total' => 100, 'status' => 'pending']);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'PF-VALIDTEST',
                'amount' => 10000,
                'currency' => 'USD',
                'channel' => 'card',
            ],
        ], 200),
    ]);

    $body = json_encode([
        'event' => 'charge.success',
        'data' => ['id' => 'evt_123', 'reference' => 'PF-VALIDTEST'],
    ]);
    $signature = paystackSignature($body, 'test_secret_key');

    $response = $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
        'HTTP_x-paystack-signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $response->assertNoContent(200);
    expect($order->fresh()->status)->toBe('paid');
});

it('processes a redelivered webhook event exactly once', function () {
    $order = Order::factory()->create(['reference' => 'PF-DUPTEST', 'total' => 50, 'status' => 'pending']);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success', 'reference' => 'PF-DUPTEST',
                'amount' => 5000, 'currency' => 'USD', 'channel' => 'card',
            ],
        ], 200),
    ]);

    $body = json_encode(['event' => 'charge.success', 'data' => ['id' => 'evt_dup', 'reference' => 'PF-DUPTEST']]);
    $signature = paystackSignature($body, 'test_secret_key');
    $headers = ['HTTP_x-paystack-signature' => $signature, 'CONTENT_TYPE' => 'application/json'];

    $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], $headers, $body)->assertNoContent(200);
    $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], $headers, $body)->assertNoContent(200);

    expect(PaymentWebhookEvent::where('event_id', 'evt_dup')->count())->toBe(1);
});

it('marks the order failed when the paystack-reported amount does not match', function () {
    $order = Order::factory()->create(['reference' => 'PF-TAMPERED', 'total' => 500, 'status' => 'pending']);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success', 'reference' => 'PF-TAMPERED',
                'amount' => 100, // attacker paid $1 instead of $500
                'currency' => 'USD', 'channel' => 'card',
            ],
        ], 200),
    ]);

    $body = json_encode(['event' => 'charge.success', 'data' => ['id' => 'evt_tamper', 'reference' => 'PF-TAMPERED']]);
    $signature = paystackSignature($body, 'test_secret_key');

    $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
        'HTTP_x-paystack-signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertNoContent(200);

    expect($order->fresh()->status)->toBe('failed');
});
