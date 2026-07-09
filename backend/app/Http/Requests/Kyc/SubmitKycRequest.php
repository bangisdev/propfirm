<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;

class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'in:passport,national_id,drivers_license'],
            'document_front' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'], // 8MB
            'document_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'selfie' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:8192'],
        ];
    }
}
