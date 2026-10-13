<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoleResource;
use App\Models\Role;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Roles are defined in App\Support\Enums\UserRole and synced with `php artisan abilities:refresh`,
 * so the API only exposes them read-only.
 */
class RoleController extends Controller
{
    public function index()
    {
        return RoleResource::collection(
            QueryBuilder::for(Role::class)
                ->allowedIncludes('abilities')
                ->orderBy('id')
                ->get()
        );
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('abilities'));
    }
}
