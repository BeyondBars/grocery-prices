<?php

namespace App\Http\Controllers\TwoFactor;

use App\Exceptions\TwoFactorFailedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactor\VerifyTwoFactorCodeRequest;
use App\Http\Resources\UserResource;
use Illuminate\Validation\ValidationException;

class VerifyController extends Controller
{
    /**
     * Verify a 2FA code.
     *
     * Two cases:
     *  - Login challenge: the user has 2FA enabled and hasn't verified this session. Only the
     *    enabled method is accepted and nothing about the user's settings changes.
     *  - Setup: verifying a code for the preferred method makes it the enabled method.
     */
    public function __invoke(VerifyTwoFactorCodeRequest $request): UserResource
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $isLoginChallenge = $request->boolean('login')
            || ($user->needsTwoFactor() && !$request->isTwoFactorVerified());

        $method = $isLoginChallenge
            ? $user->two_factor_method_enabled
            : ($request->input('method') ?? $user->two_factor_method_preferred);

        try {
            if (!$this->shouldSkipVerification()) {
                $user->verifyTwoFactorCode($request->input('code'), $method);
            }
        } catch (TwoFactorFailedException $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        if (!$isLoginChallenge) {
            $user->update([
                'two_factor_required' => true,
                'two_factor_method_enabled' => $method,
                'two_factor_method_preferred' => $method,
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('two_factor_verified', true);

        return new UserResource($user->refresh()->loadAbilities());
    }

    /**
     * Local development can skip code checks with TWO_FACTOR_SKIP=true.
     */
    protected function shouldSkipVerification(): bool
    {
        return app()->environment('local') && config('app.two_factor_skip') === true;
    }
}
