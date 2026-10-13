<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Http\Resources\UserResource;
use App\Support\Traits\ThrottlesLogins;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ThrottlesLogins;

    /**
     * Max number of login attempts allowed.
     */
    protected int $maxAttempts = 5;

    /**
     * Minutes login attempts are throttled for after hitting the max.
     */
    protected int $decayMinutes = 30;

    /**
     * Log in with email and password (Sanctum SPA session).
     *
     * When the user has 2FA enabled a code is sent straight away and every route except the 2FA
     * endpoints stays blocked (see the "2fa" middleware) until the code is verified.
     */
    public function store(AuthRequest $request, Auth $auth): UserResource
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        $guard = $auth->guard('web');

        if (!$guard->attempt($request->only('email', 'password'))) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages(['email' => [Lang::get('auth.failed')]]);
        }

        /** @var \App\Models\User $user */
        $user = $guard->user();

        if ($user->isDisabled()) {
            $guard->logout();

            throw ValidationException::withMessages(['email' => [Lang::get('auth.disabled')]]);
        }

        $request->session()->regenerate();
        $request->session()->forget('two_factor_verified');
        $this->clearLoginAttempts($request);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            // An unfinished change of 2FA method is abandoned on login.
            'two_factor_method_preferred' => $user->two_factor_method_enabled ?? $user->two_factor_method_preferred,
        ]);

        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        if ($user->needsTwoFactor()) {
            $user->sendTwoFactorCode($user->two_factor_method_enabled);
        }

        return new UserResource($user->refresh()->loadAbilities());
    }

    /**
     * Log out and destroy the session.
     */
    public function destroy(Request $request, Auth $auth): Response
    {
        $auth->guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
