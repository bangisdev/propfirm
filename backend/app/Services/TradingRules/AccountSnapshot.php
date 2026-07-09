<?php

namespace App\Services\TradingRules;

final class AccountSnapshot
{
    public function __construct(
        public readonly string $phase, // 'evaluation_1' | 'evaluation_2' | 'funded'
        public readonly float $startingBalance,
        public readonly float $phaseStartBalance,
        public readonly float $dayStartBalance,
        public readonly float $highestBalance,
        public readonly float $currentBalance,
        public readonly float $currentEquity,
        public readonly int $tradingDaysCount,
        public readonly int $minTradingDays,
        public readonly float $maxDailyDrawdownPct,
        public readonly float $maxTotalDrawdownPct,
        public readonly ?float $profitTargetPct, // null when there's no target for the current phase (e.g. funded)
        public readonly bool $hasSecondPhase,
    ) {}
}
