<?php

use App\Jobs\ProcessPayoutJob;
use App\Models\PayoutBankAccount;
use App\Models\PayoutRequest;
use App\Models\TradingAccount;
use App\Services\Payments\PaystackService;
use Illuminate\Support\Facades\Http;

it('creates a transfer recipient and initiates the transfer for an approved payout', function () {
    $bankAccount = PayoutBankAccount::factory()->create(['paystack_recipient_code' => null]);
    $payout = PayoutRequest::factory()->approved()->create([
        'bank_account_id' => $bankAccount->id,
        'trader_amount' => 400,
    ]);

    Http::fake([
        'api.paystack.co/transferrecipient' => Http::response([
            'status' => true,
            'data' => ['recipient_code' => 'RCP_test123'],
        ], 200),
        'api.paystack.co/transfer' => Http::response([
            'status' => true,
            'data' => ['transfer_code' => 'TRF_test456', 'status' => 'pending'],
        ], 200),
    ]);

    (new ProcessPayoutJob($payout->id))->handle(app(PaystackService::class));

    $fresh = $payout->fresh();
    expect($fresh->status)->toBe('processing');
    expect($fresh->paystack_transfer_code)->toBe('TRF_test456');
    expect($bankAccount->fresh()->paystack_recipient_code)->toBe('RCP_test123');
});

it('reuses an existing recipient code instead of creating a new one', function () {
    $bankAccount = PayoutBankAccount::factory()->create(['paystack_recipient_code' => 'RCP_existing']);
    $payout = PayoutRequest::factory()->approved()->create(['bank_account_id' => $bankAccount->id]);

    Http::fake([
        'api.paystack.co/transferrecipient' => Http::response(['status' => true, 'data' => []], 200),
        'api.paystack.co/transfer' => Http::response([
            'status' => true,
            'data' => ['transfer_code' => 'TRF_reused', 'status' => 'pending'],
        ], 200),
    ]);

    (new ProcessPayoutJob($payout->id))->handle(app(PaystackService::class));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'transferrecipient'));
});

it('does nothing if the payout is not in approved status', function () {
    $payout = PayoutRequest::factory()->create(['status' => 'pending']);

    (new ProcessPayoutJob($payout->id))->handle(app(PaystackService::class));

    expect($payout->fresh()->status)->toBe('pending');
});

it('marks a payout paid and rolls the baseline forward on transfer.success webhook', function () {
    config(['services.paystack.secret_key' => 'test_secret_key']);

    $account = TradingAccount::factory()->create([
        'status' => 'funded',
        'current_balance' => 102000,
    ]);
    $payout = PayoutRequest::factory()->processing()->create([
        'trading_account_id' => $account->id,
        'paystack_transfer_code' => 'TRF_webhook_test',
    ]);

    $body = json_encode([
        'event' => 'transfer.success',
        'data' => ['id' => 999, 'transfer_code' => 'TRF_webhook_test', 'reference' => 'PO-WEBHOOKTEST'],
    ]);
    $signature = hash_hmac('sha512', $body, 'test_secret_key');

    $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
        'HTTP_x-paystack-signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertNoContent(200);

    $freshPayout = $payout->fresh();
    expect($freshPayout->status)->toBe('paid');
    expect($freshPayout->paid_at)->not->toBeNull();

    $freshAccount = $account->fresh();
    expect((float) $freshAccount->payout_baseline_balance)->toBe(102000.0);
    expect($freshAccount->next_payout_eligible_at)->not->toBeNull();
});

it('marks a payout failed on transfer.failed webhook', function () {
    config(['services.paystack.secret_key' => 'test_secret_key']);

    $payout = PayoutRequest::factory()->processing()->create(['paystack_transfer_code' => 'TRF_fail_test']);

    $body = json_encode([
        'event' => 'transfer.failed',
        'data' => ['id' => 888, 'transfer_code' => 'TRF_fail_test', 'reference' => 'PO-FAILTEST'],
    ]);
    $signature = hash_hmac('sha512', $body, 'test_secret_key');

    $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
        'HTTP_x-paystack-signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertNoContent(200);

    expect($payout->fresh()->status)->toBe('failed');
});
