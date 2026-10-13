<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateSignatureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', Rule::exists('email_signatures', 'email')],
            'expires' => ['required'],
            'signature' => ['required']
        ];
    }



    public function messages(): array
    {
        return [
            'email.email' => 'The email entered must be a valid email address.',
            'email.exists' => 'The email entered email is invalid.'
        ];
    }
}
