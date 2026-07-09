<?php

namespace App\Services\TradingRules;

enum RuleEvaluationOutcome: string
{
    case NoAction = 'no_action';
    case BreachedDailyDrawdown = 'breached_daily_drawdown';
    case BreachedMaxDrawdown = 'breached_max_drawdown';
    case PassedPhase = 'passed_phase';
    case Funded = 'funded';

    public function isBreach(): bool
    {
        return in_array($this, [self::BreachedDailyDrawdown, self::BreachedMaxDrawdown], true);
    }

    public function isAdvancement(): bool
    {
        return in_array($this, [self::PassedPhase, self::Funded], true);
    }
}
