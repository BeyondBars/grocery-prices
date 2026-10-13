<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updatePassword', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min($this->user()->requiredPasswordLength())->letters()->numbers()->symbols()],
            'password_confirmation' => ['required'],
        ];
    }
}
