<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;

class DisableTwoFactorController extends Controller
{
    /**
     * Admin action: turn off another user's 2FA (e.g. they lost access to their phone).
     */
    public function __invoke(User $user): UserResource
    {
        $this->authorize('disableTwoFactor', $user);

        $user->update([
            'two_factor_required' => false,
            'two_factor_method_enabled' => null,
            'two_factor_method_preferred' => null,
        ]);

        return new UserResource($user->load('role'));
    }
}
