<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('withdrawals.approve');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
