<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->getRoleNames()->first() ?? 'trader',
            'kyc_status' => $this->kyc_status,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'referral_code' => $this->referral_code,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
