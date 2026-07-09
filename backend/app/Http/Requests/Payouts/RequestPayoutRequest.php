<?php

namespace App\Http\Requests\Payouts;

use Illuminate\Foundation\Http\FormRequest;

class RequestPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trading_account_id' => ['required', 'uuid', 'exists:trading_accounts,id'],
            'bank_account_id' => ['required', 'uuid', 'exists:payout_bank_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
