<?php

namespace App\Http\Requests\User;

use App\Rules\MobileNumber;
use App\Support\Enums\UserAbility;
use App\Support\Enums\UserRole;
use App\Support\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $target = $this->route('user');

        // Status and role can only be changed by user managers, and never on their own account.
        $canManage = $this->user()->can(UserAbility::WriteUsers->value) && !$this->user()->is($target);

        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($target->id),
                Rule::unique('users', 'new_email')->ignore($target->id),
            ],
            'mobile' => ['sometimes', 'nullable', new MobileNumber],
            'status' => [Rule::prohibitedIf(!$canManage), 'sometimes', Rule::enum(UserStatus::class)],
            'role' => [Rule::prohibitedIf(!$canManage), 'sometimes', 'nullable', Rule::enum(UserRole::class)],
        ];
    }
}
