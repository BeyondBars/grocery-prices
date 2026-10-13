<?php

namespace App\Models;

use App\Support\Enums\UserRole;
use Silber\Bouncer\Database\Role as BouncerRole;

class Role extends BouncerRole
{
    /**
     * Get the matching role enum (null for roles not defined in code).
     */
    public function enum(): ?UserRole
    {
        return UserRole::tryFrom($this->name);
    }
}
