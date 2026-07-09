<?php

namespace App\Http\Resources;

use App\Services\TradingRules\TradingRuleEngine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradingAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $engine = app(TradingRuleEngine::class);
        $snapshot = $this->resource->isProvisioned() ? $this->resource->toSnapshot() : null;

        return [
            'id' => $this->id,
            'mt5_login' => $this->mt5_login,
            'mt5_server' => $this->mt5_server,
            'phase' => $this->phase,
            'status' => $this->status,
            'account_size' => (float) $this->starting_balance,
            'current_balance' => $this->current_balance !== null ? (float) $this->current_balance : null,
            'current_equity' => $this->current_equity !== null ? (float) $this->current_equity : null,
            'trading_days_count' => $this->trading_days_count,
            'challenge' => new ChallengeResource($this->whenLoaded('challenge')),
            'provisioned_at' => $this->provisioned_at?->toIso8601String(),
            'breached_at' => $this->breached_at?->toIso8601String(),
            'breach_reason' => $this->breach_reason,
            'rule_progress' => $snapshot ? [
                'min_trading_days' => $snapshot->minTradingDays,
                'profit_target_pct' => $snapshot->profitTargetPct,
                'profit_progress_pct' => $engine->profitProgressPct($snapshot),
                'daily_drawdown_used_pct' => $engine->dailyDrawdownUsedPct($snapshot),
                'max_drawdown_used_pct' => $engine->maxDrawdownUsedPct($snapshot),
            ] : null,
        ];
    }
}
