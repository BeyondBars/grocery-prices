<?php

namespace App\Http\Requests\TwoFactor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTwoFactorMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'two_factor_required' => ['required', 'boolean'],
            'two_factor_method_preferred' => [
                'bail',
                'nullable',
                Rule::requiredIf($this->boolean('two_factor_required')),
                Rule::in(['sms', 'email']),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('two_factor_method_preferred') === 'sms' && !$this->user()->mobile) {
                    $validator->errors()->add('two_factor_method_preferred', __('auth.two_factor_no_mobile'));
                }
            },
        ];
    }
}
