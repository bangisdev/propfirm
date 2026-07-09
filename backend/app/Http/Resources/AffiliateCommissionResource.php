<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffiliateCommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'referred_user_name' => $this->whenLoaded('referredUser', fn () => $this->referredUser->name),
            'order_amount' => (float) $this->order_amount,
            'commission_pct' => (float) $this->commission_pct,
            'commission_amount' => (float) $this->commission_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
