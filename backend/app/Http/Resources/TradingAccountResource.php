<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradingAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
        ];
    }
}
