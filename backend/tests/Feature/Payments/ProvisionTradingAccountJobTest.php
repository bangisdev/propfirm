<?php

use App\Jobs\ProvisionTradingAccountJob;
use App\Models\Challenge;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Services\MT5\MT5BridgeClientInterface;
use Tests\Fakes\FakeMT5BridgeClient;

beforeEach(function () {
    FakeMT5BridgeClient::reset();
    $this->app->bind(MT5BridgeClientInterface::class, FakeMT5BridgeClient::class);
});

it('provisions a trading account with the correct starting balance from the challenge', function () {
    $challenge = Challenge::factory()->create(['account_size' => 50000]);
    $order = Order::factory()->paid()->create(['challenge_id' => $challenge->id, 'total' => 249]);

    (new ProvisionTradingAccountJob($order->id))->handle(app(MT5BridgeClientInterface::class));

    $account = TradingAccount::where('order_id', $order->id)->firstOrFail();

    expect($account->status)->toBe('active');
    expect((float) $account->starting_balance)->toBe(50000.0);
    expect($account->mt5_login)->not->toBeNull();
    expect($account->mt5_server)->toBe('PropFirm-Demo');
});

it('does not create a duplicate trading account if the job runs twice for the same order', function () {
    $order = Order::factory()->paid()->create();

    (new ProvisionTradingAccountJob($order->id))->handle(app(MT5BridgeClientInterface::class));
    (new ProvisionTradingAccountJob($order->id))->handle(app(MT5BridgeClientInterface::class));

    expect(TradingAccount::where('order_id', $order->id)->count())->toBe(1);
});

it('does nothing if the order is not actually paid', function () {
    $order = Order::factory()->create(['status' => 'pending']);

    (new ProvisionTradingAccountJob($order->id))->handle(app(MT5BridgeClientInterface::class));

    expect(TradingAccount::where('order_id', $order->id)->count())->toBe(0);
});

it('marks the account disabled for manual follow-up when provisioning permanently fails', function () {
    $order = Order::factory()->paid()->create();
    $job = new ProvisionTradingAccountJob($order->id);

    FakeMT5BridgeClient::$shouldFail = true;

    try {
        $job->handle(app(MT5BridgeClientInterface::class));
    } catch (\Throwable) {
        // expected — simulates the queue worker giving up after exhausting retries
        $job->failed(new \RuntimeException('exhausted retries'));
    }

    $account = TradingAccount::where('order_id', $order->id)->first();
    expect($account->status)->toBe('disabled');
});
