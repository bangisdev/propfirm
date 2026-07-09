<?php

namespace App\Http\Requests\Payouts;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_code' => ['required', 'string', 'max:10'],
            'account_number' => ['required', 'string', 'min:6', 'max:20'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ];
    }
}
