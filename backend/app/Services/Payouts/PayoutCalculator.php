<?php

namespace App\Services\Payouts;

final class PayoutCalculator
{
    /**
     * Splits a requested payout amount according to the profit split percentage.
     * The trader's share is rounded DOWN to the cent (never up) — any fractional
     * cent from the split goes to the firm's share, so trader_amount + firm_amount
     * always sums exactly to the requested amount with no rounding drift, and the
     * firm never accidentally overpays due to floating point rounding.
     *
     * @return array{trader_amount: float, firm_amount: float}
     */
    public function split(float $requestedAmount, float $profitSplitPct): array
    {
        $traderAmount = floor($requestedAmount * $profitSplitPct / 100 * 100) / 100;
        $firmAmount = round($requestedAmount - $traderAmount, 2);

        return [
            'trader_amount' => $traderAmount,
            'firm_amount' => $firmAmount,
        ];
    }
}
