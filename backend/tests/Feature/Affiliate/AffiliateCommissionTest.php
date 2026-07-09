<?php

use App\Models\AffiliateCommission;
use App\Models\Order;
use App\Models\User;
use App\Services\Affiliate\AffiliateService;

beforeEach(function () {
    $this->service = app(AffiliateService::class);
    config(['affiliate.commission_pct' => 10.0, 'affiliate.first_order_only' => true]);
});

it('records a commission when a referred user pays their first order', function () {
    $affiliate = User::factory()->create();
    $referred = User::factory()->create(['referred_by' => $affiliate->id]);
    $order = Order::factory()->create(['user_id' => $referred->id, 'status' => 'paid', 'total' => 250]);

    $commission = $this->service->recordCommissionForOrder($order);

    expect($commission)->not->toBeNull();
    expect((float) $commission->commission_amount)->toBe(25.0); // 10% of 250
    expect($commission->affiliate_user_id)->toBe($affiliate->id);
});

it('does not record a commission for a user with no referrer', function () {
    $user = User::factory()->create(['referred_by' => null]);
    $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'paid', 'total' => 250]);

    $commission = $this->service->recordCommissionForOrder($order);

    expect($commission)->toBeNull();
    expect(AffiliateCommission::count())->toBe(0);
});

it('does not record a second commission for a repeat purchase when first_order_only is enabled', function () {
    $affiliate = User::factory()->create();
    $referred = User::factory()->create(['referred_by' => $affiliate->id]);

    $firstOrder = Order::factory()->create(['user_id' => $referred->id, 'status' => 'paid', 'total' => 100]);
    $this->service->recordCommissionForOrder($firstOrder);

    $secondOrder = Order::factory()->create(['user_id' => $referred->id, 'status' => 'paid', 'total' => 200]);
    $commission = $this->service->recordCommissionForOrder($secondOrder);

    expect($commission)->toBeNull();
    expect(AffiliateCommission::count())->toBe(1);
});

it('does not record a duplicate commission for the same order', function () {
    $affiliate = User::factory()->create();
    $referred = User::factory()->create(['referred_by' => $affiliate->id]);
    $order = Order::factory()->create(['user_id' => $referred->id, 'status' => 'paid', 'total' => 100]);

    $this->service->recordCommissionForOrder($order);
    $second = $this->service->recordCommissionForOrder($order);

    expect($second)->toBeNull();
    expect(AffiliateCommission::count())->toBe(1);
});

it('records commissions for repeat purchases when first_order_only is disabled', function () {
    config(['affiliate.first_order_only' => false]);

    $affiliate = User::factory()->create();
    $referred = User::factory()->create(['referred_by' => $affiliate->id]);

    $firstOrder = Order::factory()->create(['user_id' => $referred->id, 'status' => 'paid', 'total' => 100]);
    $this->service->recordCommissionForOrder($firstOrder);

    $secondOrder = Order::factory()->create(['user_id' => $referred->id, 'status' => 'paid', 'total' => 200]);
    $this->service->recordCommissionForOrder($secondOrder);

    expect(AffiliateCommission::count())->toBe(2);
});
