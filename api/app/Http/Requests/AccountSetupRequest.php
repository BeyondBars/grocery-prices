<?php

namespace App\Http\Requests;

use App\Rules\MobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', new MobileNumber],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
            'password_confirmation' => ['required'],
            'agree_terms' => ['accepted'],
        ];
    }
}
