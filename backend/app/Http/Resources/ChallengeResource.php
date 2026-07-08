<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'phase_count' => $this->phase_count,
            'account_size' => (float) $this->account_size,
            'currency' => $this->currency,
            'price' => (float) $this->price,
            'rules' => [
                'profit_target_phase1_pct' => (float) $this->profit_target_phase1_pct,
                'profit_target_phase2_pct' => $this->profit_target_phase2_pct !== null
                    ? (float) $this->profit_target_phase2_pct : null,
                'max_daily_drawdown_pct' => (float) $this->max_daily_drawdown_pct,
                'max_total_drawdown_pct' => (float) $this->max_total_drawdown_pct,
                'min_trading_days' => $this->min_trading_days,
                'profit_split_pct' => (float) $this->profit_split_pct,
                'news_trading_restricted' => $this->news_trading_restricted,
                'weekend_holding_allowed' => $this->weekend_holding_allowed,
            ],
        ];
    }
}
