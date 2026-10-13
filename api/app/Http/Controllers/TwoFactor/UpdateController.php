<?php

namespace App\Http\Controllers\TwoFactor;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactor\UpdateTwoFactorMethodRequest;
use App\Http\Resources\UserResource;

class UpdateController extends Controller
{
    /**
     * Opt in/out of 2FA and choose the preferred method.
     *
     * Choosing a method only sets it as "preferred"; it becomes "enabled" once a code sent via
     * that method is verified (see VerifyController).
     */
    public function __invoke(UpdateTwoFactorMethodRequest $request): UserResource
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->two_factor_required === false) {
            $user->fill(['two_factor_method_enabled' => null, 'two_factor_method_preferred' => null]);
        }

        $user->save();

        return new UserResource($user->refresh()->loadAbilities());
    }
}
