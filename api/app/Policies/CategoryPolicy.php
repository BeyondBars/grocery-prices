<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Support\Enums\UserAbility;

/**
 * Categories shape the whole catalog, so only catalog managers change them.
 */
class CategoryPolicy
{
    public function create(User $user): bool
    {
        return $user->can(UserAbility::ManageCatalog->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can(UserAbility::ManageCatalog->value);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can(UserAbility::ManageCatalog->value);
    }
}
