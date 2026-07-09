<?php

use App\Models\Challenge;
use App\Models\PayoutBankAccount;
use App\Models\TradingAccount;
use App\Services\Payouts\PayoutService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(PayoutService::class);
});

function fundedAccount(array $overrides = []): TradingAccount
{
    $challenge = Challenge::factory()->create(array_merge([
        'profit_split_pct' => 80,
        'min_payout_amount' => 50,
        'payout_cycle_days' => 14,
    ], $overrides['challenge'] ?? []));

    return TradingAccount::factory()->create(array_merge([
        'challenge_id' => $challenge->id,
        'status' => 'funded',
        'phase' => 'funded',
        'starting_balance' => 100000,
        'current_balance' => 102000,
        'payout_baseline_balance' => 100000, // $2,000 available profit
        'next_payout_eligible_at' => null,
    ], $overrides['account'] ?? []));
}

it('allows a valid payout request within available profit', function () {
    $account = fundedAccount();
    $bankAccount = PayoutBankAccount::factory()->create(['user_id' => $account->user_id]);

    $payout = $this->service->request($account, $bankAccount, 500);

    expect($payout->status)->toBe('pending');
    expect((float) $payout->trader_amount)->toBe(400.0); // 80% of 500
    expect((float) $payout->firm_amount)->toBe(100.0);
});

it('rejects a payout request for a non-funded account', function () {
    $account = fundedAccount(['account' => ['status' => 'active', 'phase' => 'evaluation_1']]);
    $bankAccount = PayoutBankAccount::factory()->create(['user_id' => $account->user_id]);

    $this->service->request($account, $bankAccount, 100);
})->throws(ValidationException::class);

it('rejects a payout request below the minimum amount', function () {
    $account = fundedAccount(['challenge' => ['min_payout_amount' => 50]]);
    $bankAccount = PayoutBankAccount::factory()->create(['user_id' => $account->user_id]);

    $this->service->request($account, $bankAccount, 10);
})->throws(ValidationException::class);

it('rejects a payout request exceeding available profit', function () {
    $account = fundedAccount(); // $2,000 available
    $bankAccount = PayoutBankAccount::factory()->create(['user_id' => $account->user_id]);

    $this->service->request($account, $bankAccount, 5000);
})->throws(ValidationException::class);

it('rejects a second payout request while one is already pending', function () {
    $account = fundedAccount();
    $bankAccount = PayoutBankAccount::factory()->create(['user_id' => $account->user_id]);

    $this->service->request($account, $bankAccount, 500);

    $this->service->request($account, $bankAccount, 500);
})->throws(ValidationException::class);

it('rejects a payout request before the cooldown period has elapsed', function () {
    $account = fundedAccount(['account' => ['next_payout_eligible_at' => now()->addDays(5)]]);
    $bankAccount = PayoutBankAccount::factory()->create(['user_id' => $account->user_id]);

    $this->service->request($account, $bankAccount, 500);
})->throws(ValidationException::class);

it('allows a payout request once the cooldown has elapsed', function () {
    $account = fundedAccount(['account' => ['next_payout_eligible_at' => now()->subDay()]]);
    $bankAccount = PayoutBankAccount::factory()->create(['user_id' => $account->user_id]);

    $payout = $this->service->request($account, $bankAccount, 500);

    expect($payout->status)->toBe('pending');
});
