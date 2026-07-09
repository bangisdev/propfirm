<?php

use App\Services\TradingRules\AccountSnapshot;
use App\Services\TradingRules\RuleEvaluationOutcome;
use App\Services\TradingRules\TradingRuleEngine;

beforeEach(function () {
    $this->engine = new TradingRuleEngine();
});

function baseSnapshot(array $overrides = []): AccountSnapshot
{
    return new AccountSnapshot(
        phase: $overrides['phase'] ?? 'evaluation_1',
        startingBalance: $overrides['startingBalance'] ?? 10000.0,
        phaseStartBalance: $overrides['phaseStartBalance'] ?? 10000.0,
        dayStartBalance: $overrides['dayStartBalance'] ?? 10000.0,
        highestBalance: $overrides['highestBalance'] ?? 10000.0,
        currentBalance: $overrides['currentBalance'] ?? 10000.0,
        currentEquity: $overrides['currentEquity'] ?? 10000.0,
        tradingDaysCount: $overrides['tradingDaysCount'] ?? 5,
        minTradingDays: $overrides['minTradingDays'] ?? 5,
        maxDailyDrawdownPct: $overrides['maxDailyDrawdownPct'] ?? 5.0,
        maxTotalDrawdownPct: $overrides['maxTotalDrawdownPct'] ?? 10.0,
        profitTargetPct: array_key_exists('profitTargetPct', $overrides) ? $overrides['profitTargetPct'] : 10.0,
        hasSecondPhase: $overrides['hasSecondPhase'] ?? true,
    );
}

it('takes no action when everything is within limits and target not yet reached', function () {
    $snapshot = baseSnapshot(['currentEquity' => 10200.0, 'currentBalance' => 10200.0]); // +2%, target is 10%

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::NoAction);
});

it('breaches max total drawdown when equity falls below the static floor', function () {
    // 10% max drawdown on $10,000 starting balance = floor of $9,000
    $snapshot = baseSnapshot(['currentEquity' => 8999.0]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::BreachedMaxDrawdown);
});

it('does not breach max drawdown exactly at the floor', function () {
    $snapshot = baseSnapshot(['currentEquity' => 9000.0]);

    expect($this->engine->evaluate($snapshot))->not->toBe(RuleEvaluationOutcome::BreachedMaxDrawdown);
});

it('breaches daily drawdown when equity falls below the daily floor', function () {
    // 5% daily allowance on $10,000 = $500; day started at $10,000 balance -> floor $9,500
    $snapshot = baseSnapshot(['dayStartBalance' => 10000.0, 'currentEquity' => 9499.0]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::BreachedDailyDrawdown);
});

it('measures the daily floor against the day start balance, not the current balance', function () {
    // Account has grown to $11,000 balance, but today started at $10,000 —
    // the $500 (5%) daily allowance is still based on the $10,000 starting balance.
    $snapshot = baseSnapshot([
        'startingBalance' => 10000.0,
        'dayStartBalance' => 10000.0,
        'currentBalance' => 11000.0,
        'currentEquity' => 9499.0,
    ]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::BreachedDailyDrawdown);
});

it('prioritizes max drawdown breach over daily drawdown breach when both are triggered', function () {
    $snapshot = baseSnapshot([
        'startingBalance' => 10000.0,
        'dayStartBalance' => 10000.0,
        'currentEquity' => 8000.0, // breaches both max (floor 9000) and daily (floor 9500)
    ]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::BreachedMaxDrawdown);
});

it('passes phase 1 into phase 2 when profit target and min trading days are both met', function () {
    $snapshot = baseSnapshot([
        'phase' => 'evaluation_1',
        'phaseStartBalance' => 10000.0,
        'currentBalance' => 11000.0, // +10%, exactly at target
        'currentEquity' => 11000.0,
        'tradingDaysCount' => 5,
        'minTradingDays' => 5,
        'profitTargetPct' => 10.0,
        'hasSecondPhase' => true,
    ]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::PassedPhase);
});

it('does not pass phase 1 if profit target is met but minimum trading days are not', function () {
    $snapshot = baseSnapshot([
        'currentBalance' => 11000.0,
        'currentEquity' => 11000.0,
        'tradingDaysCount' => 3,
        'minTradingDays' => 5,
        'profitTargetPct' => 10.0,
    ]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::NoAction);
});

it('does not pass phase 1 if minimum trading days are met but profit target is not', function () {
    $snapshot = baseSnapshot([
        'currentBalance' => 10500.0,
        'currentEquity' => 10500.0,
        'tradingDaysCount' => 5,
        'minTradingDays' => 5,
        'profitTargetPct' => 10.0,
    ]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::NoAction);
});

it('goes straight to funded when a one-step challenge passes phase 1', function () {
    $snapshot = baseSnapshot([
        'phase' => 'evaluation_1',
        'currentBalance' => 11000.0,
        'currentEquity' => 11000.0,
        'profitTargetPct' => 10.0,
        'hasSecondPhase' => false, // 1-step challenge
    ]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::Funded);
});

it('becomes funded when phase 2 of a two-step challenge passes', function () {
    $snapshot = baseSnapshot([
        'phase' => 'evaluation_2',
        'phaseStartBalance' => 11000.0,
        'currentBalance' => 11550.0, // +5% from phase 2 start
        'currentEquity' => 11550.0,
        'profitTargetPct' => 5.0,
        'hasSecondPhase' => true,
    ]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::Funded);
});

it('measures phase 2 profit target from the phase start balance, not the original starting balance', function () {
    // Started at $10,000, passed phase 1 at $11,000. Phase 2 target is 5% of $11,000 = $550 gain.
    // Current balance of $11,400 is only +3.6% from phase start, despite being +14% overall.
    $snapshot = baseSnapshot([
        'phase' => 'evaluation_2',
        'startingBalance' => 10000.0,
        'phaseStartBalance' => 11000.0,
        'currentBalance' => 11400.0,
        'currentEquity' => 11400.0,
        'profitTargetPct' => 5.0,
    ]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::NoAction);
});

it('does nothing for a funded account with no profit target', function () {
    $snapshot = baseSnapshot([
        'phase' => 'funded',
        'currentBalance' => 15000.0,
        'currentEquity' => 15000.0,
        'profitTargetPct' => null,
    ]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::NoAction);
});

it('still breaches drawdown rules even for a funded account', function () {
    $snapshot = baseSnapshot([
        'phase' => 'funded',
        'startingBalance' => 10000.0,
        'currentEquity' => 8500.0, // below the 10% max drawdown floor
        'profitTargetPct' => null,
    ]);

    expect($this->engine->evaluate($snapshot))->toBe(RuleEvaluationOutcome::BreachedMaxDrawdown);
});

describe('progress percentage helpers', function () {
    it('calculates profit progress percentage relative to phase start balance', function () {
        $snapshot = baseSnapshot(['phaseStartBalance' => 10000.0, 'currentBalance' => 10500.0]);

        expect($this->engine->profitProgressPct($snapshot))->toBe(5.0);
    });

    it('calculates daily drawdown used percentage', function () {
        // $500 allowance (5% of 10,000), used $250 of it (equity down from 10,000 to 9,750) = 50%
        $snapshot = baseSnapshot(['dayStartBalance' => 10000.0, 'currentEquity' => 9750.0]);

        expect($this->engine->dailyDrawdownUsedPct($snapshot))->toBe(50.0);
    });

    it('calculates max drawdown used percentage', function () {
        // $1,000 allowance (10% of 10,000), used $500 of it = 50%
        $snapshot = baseSnapshot(['startingBalance' => 10000.0, 'currentEquity' => 9500.0]);

        expect($this->engine->maxDrawdownUsedPct($snapshot))->toBe(50.0);
    });

    it('does not report negative used percentage when equity is above the baseline', function () {
        $snapshot = baseSnapshot(['dayStartBalance' => 10000.0, 'currentEquity' => 10500.0]);

        expect($this->engine->dailyDrawdownUsedPct($snapshot))->toBe(0.0);
    });
});
