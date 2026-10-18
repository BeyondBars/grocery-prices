<?php

namespace App\Policies;

use App\Models\ShoppingList;
use App\Models\User;

/**
 * Shopping lists are private to the user who made them.
 */
class ShoppingListPolicy
{
    public function view(User $user, ShoppingList $list): bool
    {
        return $list->user_id === $user->id;
    }

    public function update(User $user, ShoppingList $list): bool
    {
        return $list->user_id === $user->id;
    }

    public function delete(User $user, ShoppingList $list): bool
    {
        return $list->user_id === $user->id;
    }
}
