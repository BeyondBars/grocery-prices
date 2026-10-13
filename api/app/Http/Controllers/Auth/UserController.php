<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * The logged in user, with role and merged abilities.
     */
    public function __invoke(Request $request): UserResource
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return new UserResource($user->loadAbilities());
    }
}
