<?php

use App\Models\AffiliateCommission;
use App\Models\PayoutBankAccount;
use App\Models\User;
use App\Services\Affiliate\AffiliatePayoutService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(AffiliatePayoutService::class);
});

it('batches all pending commissions into a single transfer', function () {
    $affiliate = User::factory()->create();
    PayoutBankAccount::factory()->create(['user_id' => $affiliate->id, 'is_default' => true]);

    AffiliateCommission::factory()->count(3)->create([
        'affiliate_user_id' => $affiliate->id,
        'status' => 'pending',
        'commission_amount' => 20,
    ]);

    Http::fake([
        'api.paystack.co/transferrecipient' => Http::response(['status' => true, 'data' => ['recipient_code' => 'RCP_aff']], 200),
        'api.paystack.co/transfer' => Http::response(['status' => true, 'data' => ['transfer_code' => 'TRF_aff', 'status' => 'pending']], 200),
    ]);

    $result = $this->service->payoutPending($affiliate);

    expect($result['total_amount'])->toBe(60.0);
    expect($result['commission_count'])->toBe(3);
    expect(AffiliateCommission::where('affiliate_user_id', $affiliate->id)->where('status', 'processing')->count())->toBe(3);
});

it('throws when there are no pending commissions', function () {
    $affiliate = User::factory()->create();
    PayoutBankAccount::factory()->create(['user_id' => $affiliate->id]);

    $this->service->payoutPending($affiliate);
})->throws(ValidationException::class);

it('throws when the affiliate has no bank account on file', function () {
    $affiliate = User::factory()->create();
    AffiliateCommission::factory()->create(['affiliate_user_id' => $affiliate->id, 'status' => 'pending']);

    $this->service->payoutPending($affiliate);
})->throws(ValidationException::class);

it('reuses an existing recipient code for a repeat affiliate payout', function () {
    $affiliate = User::factory()->create();
    PayoutBankAccount::factory()->create([
        'user_id' => $affiliate->id,
        'is_default' => true,
        'paystack_recipient_code' => 'RCP_existing',
    ]);
    AffiliateCommission::factory()->create(['affiliate_user_id' => $affiliate->id, 'status' => 'pending']);

    Http::fake([
        'api.paystack.co/transfer' => Http::response(['status' => true, 'data' => ['transfer_code' => 'TRF_x', 'status' => 'pending']], 200),
    ]);

    $this->service->payoutPending($affiliate);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'transferrecipient'));
});
