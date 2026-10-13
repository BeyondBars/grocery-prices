<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $length = User::where('email', $this->input('email'))->first()?->requiredPasswordLength() ?? 8;

        return [
            'email' => ['required', 'email'],
            'token' => ['required'],
            'password' => ['required', 'confirmed', Password::min($length)->letters()->numbers()->symbols()],
            'password_confirmation' => ['required'],
        ];
    }
}
