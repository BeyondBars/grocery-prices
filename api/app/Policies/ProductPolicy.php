<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Support\Enums\UserAbility;

/**
 * The catalog is shared by all users: anyone can add to it, only the creator or a
 * catalog manager can edit an entry, and only a catalog manager can delete one.
 */
class ProductPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Product $model): bool
    {
        return $model->created_by === $user->id || $user->can(UserAbility::ManageCatalog->value);
    }

    public function delete(User $user, Product $model): bool
    {
        return $user->can(UserAbility::ManageCatalog->value);
    }

    /**
     * Merging a duplicate moves every receipt and price to another product.
     */
    public function merge(User $user, Product $model): bool
    {
        return $user->can(UserAbility::ManageCatalog->value);
    }
}
