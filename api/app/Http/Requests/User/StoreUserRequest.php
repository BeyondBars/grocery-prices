<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Rules\MobileNumber;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email'), Rule::unique('users', 'new_email')],
            'mobile' => ['nullable', new MobileNumber],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
