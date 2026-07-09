<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trading_account_id' => $this->trading_account_id,
            'requested_amount' => (float) $this->requested_amount,
            'profit_split_pct' => (float) $this->profit_split_pct,
            'trader_amount' => (float) $this->trader_amount,
            'firm_amount' => (float) $this->firm_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'admin_notes' => $this->when(
                $request->user()?->can('withdrawals.approve') || $this->status === 'rejected',
                $this->admin_notes,
            ),
            'bank_account' => new PayoutBankAccountResource($this->whenLoaded('bankAccount')),
            'trader' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
