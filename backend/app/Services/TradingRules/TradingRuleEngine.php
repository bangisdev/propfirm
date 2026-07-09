<?php

namespace App\Services\TradingRules;

/**
 * Evaluation order matters: breaches are checked before phase advancement, so an
 * account that blew through its drawdown limit on the same tick it happened to
 * touch its profit target still fails — safety rules always win over progress.
 *
 * Conventions used here (documented since prop firms vary on these):
 * - Max total drawdown is STATIC, measured against the account's original
 *   starting balance (equity must never fall below starting_balance * (1 - max%)).
 *   This matches how most two-step evaluations define it during the evaluation
 *   phases (as opposed to a "trailing" drawdown that follows the high-water mark).
 * - Max daily drawdown allowance is a fixed percentage OF THE STARTING BALANCE
 *   (not of the day's opening balance), subtracted from the day's opening balance
 *   to get today's floor. This mirrors FTMO-style documented rules, where the
 *   daily loss limit is a fixed amount regardless of how the account has grown.
 * - Each phase's profit target is measured from the balance at the moment that
 *   phase began (`phaseStartBalance`), not from the account's original balance —
 *   so Phase 2's target is on top of Phase 1's gains, not cumulative from zero.
 */
final class TradingRuleEngine
{
    public function evaluate(AccountSnapshot $s): RuleEvaluationOutcome
    {
        if ($this->maxDrawdownBreached($s)) {
            return RuleEvaluationOutcome::BreachedMaxDrawdown;
        }

        if ($this->dailyDrawdownBreached($s)) {
            return RuleEvaluationOutcome::BreachedDailyDrawdown;
        }

        if ($this->hasPassedCurrentPhase($s)) {
            return $s->phase === 'evaluation_1' && $s->hasSecondPhase
                ? RuleEvaluationOutcome::PassedPhase
                : RuleEvaluationOutcome::Funded;
        }

        return RuleEvaluationOutcome::NoAction;
    }

    public function maxDrawdownBreached(AccountSnapshot $s): bool
    {
        return $s->currentEquity < $this->maxDrawdownFloor($s);
    }

    public function dailyDrawdownBreached(AccountSnapshot $s): bool
    {
        return $s->currentEquity < $this->dailyDrawdownFloor($s);
    }

    public function hasPassedCurrentPhase(AccountSnapshot $s): bool
    {
        if ($s->profitTargetPct === null) {
            return false; // funded accounts (or misconfigured challenges) have nothing left to "pass"
        }

        return $s->tradingDaysCount >= $s->minTradingDays
            && $this->profitProgressPct($s) >= $s->profitTargetPct;
    }

    public function maxDrawdownFloor(AccountSnapshot $s): float
    {
        return round($s->startingBalance * (1 - $s->maxTotalDrawdownPct / 100), 2);
    }

    public function dailyDrawdownFloor(AccountSnapshot $s): float
    {
        $allowance = $s->startingBalance * ($s->maxDailyDrawdownPct / 100);

        return round($s->dayStartBalance - $allowance, 2);
    }

    /** How much of the allowed max drawdown has been used, 0-100+ (can exceed 100 once breached). */
    public function maxDrawdownUsedPct(AccountSnapshot $s): float
    {
        $allowance = $s->startingBalance * ($s->maxTotalDrawdownPct / 100);
        if ($allowance <= 0) {
            return 0.0;
        }
        $used = max(0, $s->startingBalance - $s->currentEquity);

        return round(($used / $allowance) * 100, 2);
    }

    /** How much of today's allowed daily drawdown has been used, 0-100+. */
    public function dailyDrawdownUsedPct(AccountSnapshot $s): float
    {
        $allowance = $s->startingBalance * ($s->maxDailyDrawdownPct / 100);
        if ($allowance <= 0) {
            return 0.0;
        }
        $used = max(0, $s->dayStartBalance - $s->currentEquity);

        return round(($used / $allowance) * 100, 2);
    }

    /** Progress toward the current phase's profit target, as a percentage gain from phaseStartBalance. */
    public function profitProgressPct(AccountSnapshot $s): float
    {
        if ($s->phaseStartBalance <= 0) {
            return 0.0;
        }

        return round((($s->currentBalance - $s->phaseStartBalance) / $s->phaseStartBalance) * 100, 2);
    }
}
