<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('withdrawals.approve');
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
