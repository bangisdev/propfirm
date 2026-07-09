<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutBankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'account_name' => $this->account_name,
            'masked_account_number' => $this->masked_account_number,
            'currency' => $this->currency,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
