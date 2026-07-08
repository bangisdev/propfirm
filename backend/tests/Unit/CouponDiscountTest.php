<?php

use App\Models\Coupon;

it('calculates a percentage discount correctly', function () {
    $coupon = Coupon::factory()->percentageOff(25)->make();

    expect($coupon->calculateDiscount(200))->toBe(50.0);
});

it('calculates a fixed discount', function () {
    $coupon = Coupon::factory()->fixedOff(30)->make();

    expect($coupon->calculateDiscount(200))->toBe(30.0);
});

it('never discounts more than the subtotal itself', function () {
    $coupon = Coupon::factory()->fixedOff(500)->make();

    expect($coupon->calculateDiscount(200))->toBe(200.0);
});

it('rounds percentage discounts to 2 decimal places', function () {
    $coupon = Coupon::factory()->percentageOff(33)->make();

    expect($coupon->calculateDiscount(99.99))->toBe(33.0);
});
