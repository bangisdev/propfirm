<?php

use App\Models\Challenge;
use App\Models\TradingAccount;
use App\Services\MT5\MT5BridgeClientInterface;
use App\Services\TradingRules\TradingAccountSyncService;
use Tests\Fakes\FakeMT5BridgeClient;

beforeEach(function () {
    FakeMT5BridgeClient::reset();
    $this->app->bind(MT5BridgeClientInterface::class, FakeMT5BridgeClient::class);
    $this->syncService = app(TradingAccountSyncService::class);
});

function makeActiveAccount(array $overrides = []): TradingAccount
{
    $challenge = Challenge::factory()->create(array_merge([
        'account_size' => 10000,
        'max_daily_drawdown_pct' => 5,
        'max_total_drawdown_pct' => 10,
        'profit_target_phase1_pct' => 10,
        'profit_target_phase2_pct' => 5,
        'min_trading_days' => 5,
        'phase_count' => 2,
    ], $overrides['challenge'] ?? []));

    return TradingAccount::factory()->create(array_merge([
        'challenge_id' => $challenge->id,
        'status' => 'active',
        'phase' => 'evaluation_1',
        'mt5_login' => 555000,
        'starting_balance' => 10000,
        'current_balance' => 10000,
        'current_equity' => 10000,
        'highest_balance' => 10000,
        'day_start_balance' => 10000,
        'day_start_date' => now()->toDateString(),
        'phase_start_balance' => 10000,
        'trading_days_count' => 5,
    ], $overrides['account'] ?? []));
}

it('does nothing when the account is well within all limits', function () {
    $account = makeActiveAccount();
    FakeMT5BridgeClient::$stateOverride = ['balance' => 10200, 'equity' => 10200, 'open_positions' => 1, 'last_trade_at' => null];

    $outcome = $this->syncService->sync($account);

    expect($outcome->value)->toBe('no_action');
    expect($account->fresh()->status)->toBe('active');
});

it('disables the MT5 account and marks it breached on a max drawdown violation', function () {
    $account = makeActiveAccount();
    FakeMT5BridgeClient::$stateOverride = ['balance' => 8500, 'equity' => 8500, 'open_positions' => 0, 'last_trade_at' => null];

    $outcome = $this->syncService->sync($account);

    expect($outcome->value)->toBe('breached_max_drawdown');

    $fresh = $account->fresh();
    expect($fresh->status)->toBe('breached');
    expect($fresh->breach_reason)->toContain('total drawdown');
    expect(FakeMT5BridgeClient::$disabledLogins)->toContain(555000);
});

it('marks the account breached on a daily drawdown violation', function () {
    $account = makeActiveAccount(['account' => ['day_start_balance' => 10000]]);
    // 5% of 10,000 = 500 daily allowance -> floor 9,500
    FakeMT5BridgeClient::$stateOverride = ['balance' => 9400, 'equity' => 9400, 'open_positions' => 0, 'last_trade_at' => null];

    $outcome = $this->syncService->sync($account);

    expect($outcome->value)->toBe('breached_daily_drawdown');
    expect($account->fresh()->status)->toBe('breached');
});

it('advances from evaluation_1 to evaluation_2 when the phase 1 target is met', function () {
    $account = makeActiveAccount();
    FakeMT5BridgeClient::$stateOverride = ['balance' => 11000, 'equity' => 11000, 'open_positions' => 0, 'last_trade_at' => null];

    $outcome = $this->syncService->sync($account);

    expect($outcome->value)->toBe('passed_phase');

    $fresh = $account->fresh();
    expect($fresh->phase)->toBe('evaluation_2');
    expect($fresh->status)->toBe('active');
    expect((float) $fresh->phase_start_balance)->toBe(11000.0);
    expect($fresh->trading_days_count)->toBe(0); // resets for the new phase
});

it('becomes funded when evaluation_2 target is met', function () {
    $account = makeActiveAccount(['account' => [
        'phase' => 'evaluation_2',
        'phase_start_balance' => 11000,
        'current_balance' => 11000,
        'trading_days_count' => 5,
    ]]);
    FakeMT5BridgeClient::$stateOverride = ['balance' => 11550, 'equity' => 11550, 'open_positions' => 0, 'last_trade_at' => null];

    $outcome = $this->syncService->sync($account);

    expect($outcome->value)->toBe('funded');

    $fresh = $account->fresh();
    expect($fresh->phase)->toBe('funded');
    expect($fresh->status)->toBe('funded');
});

it('increments trading days count only once per calendar day of activity', function () {
    $account = makeActiveAccount(['account' => ['trading_days_count' => 2, 'last_activity_date' => null]]);
    $tradeTime = now()->subHours(2)->toIso8601String();
    FakeMT5BridgeClient::$stateOverride = ['balance' => 10100, 'equity' => 10100, 'open_positions' => 1, 'last_trade_at' => $tradeTime];

    $this->syncService->sync($account);
    $afterFirst = $account->fresh()->trading_days_count;

    // Second sync same day, same last_trade_at date — should NOT increment again.
    $this->syncService->sync($account->fresh());
    $afterSecond = $account->fresh()->trading_days_count;

    expect($afterFirst)->toBe(3);
    expect($afterSecond)->toBe(3);
});

it('does nothing for an account that is not active (already breached)', function () {
    $account = makeActiveAccount(['account' => ['status' => 'breached']]);
    FakeMT5BridgeClient::$stateOverride = ['balance' => 5000, 'equity' => 5000, 'open_positions' => 0, 'last_trade_at' => null];

    $outcome = $this->syncService->sync($account);

    expect($outcome->value)->toBe('no_action');
});
