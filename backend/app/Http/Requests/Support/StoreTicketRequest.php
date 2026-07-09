<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:technical,billing,trading,kyc,other'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
