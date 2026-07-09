<?php

use App\Services\Payouts\PayoutCalculator;

beforeEach(function () {
    $this->calculator = new PayoutCalculator();
});

it('splits a clean amount with no rounding needed', function () {
    $result = $this->calculator->split(500.00, 80.0);

    expect($result['trader_amount'])->toBe(400.0);
    expect($result['firm_amount'])->toBe(100.0);
});

it('always sums exactly back to the requested amount even with awkward splits', function () {
    // 33.333...% of $100 = $33.33333... — must not silently create or lose a cent.
    $result = $this->calculator->split(100.00, 33.333);

    expect(round($result['trader_amount'] + $result['firm_amount'], 2))->toBe(100.0);
});

it('rounds the trader share down, never up, on a fractional cent', function () {
    // 80% of $10.005 = $8.004 -> trader must get $8.00, not $8.01 (never round in the trader's favor at the firm's expense by accident).
    $result = $this->calculator->split(10.005, 80.0);

    expect($result['trader_amount'])->toBeLessThanOrEqual(8.00);
});

it('gives the firm 100% when profit split is 0%', function () {
    $result = $this->calculator->split(200.00, 0.0);

    expect($result['trader_amount'])->toBe(0.0);
    expect($result['firm_amount'])->toBe(200.0);
});

it('gives the trader everything when profit split is 100%', function () {
    $result = $this->calculator->split(200.00, 100.0);

    expect($result['trader_amount'])->toBe(200.0);
    expect($result['firm_amount'])->toBe(0.0);
});
