<?php

namespace App\Http\Requests\TwoFactor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyTwoFactorCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'method' => ['nullable', Rule::in(['email', 'sms'])],
            'login' => ['sometimes', 'boolean'],
        ];
    }
}
