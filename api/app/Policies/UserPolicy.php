<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Enums\UserAbility;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function list(User $user): bool
    {
        return $user->can(UserAbility::ReadUsers->value);
    }

    public function show(User $user, User $model): bool
    {
        return $user->is($model) || $user->can(UserAbility::ReadUsers->value);
    }

    public function create(User $user): bool
    {
        return $user->can(UserAbility::WriteUsers->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->is($model) || $user->can(UserAbility::WriteUsers->value);
    }

    public function delete(User $user, User $model): bool
    {
        return !$user->is($model) && $user->can(UserAbility::WriteUsers->value);
    }

    /**
     * Users can only change their own password (others use the reset-password email).
     */
    public function updatePassword(User $user, User $model): bool
    {
        return $user->is($model);
    }

    /**
     * Admins can turn off another user's 2FA. Users manage their own via PUT /two-factor.
     */
    public function disableTwoFactor(User $user, User $model): bool
    {
        return !$user->is($model) && $user->can(UserAbility::WriteUsers->value);
    }
}
