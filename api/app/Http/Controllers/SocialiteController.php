<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Enums\UserRole;
use App\Support\Enums\UserStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialiteController extends Controller
{
    /**
     * Providers that can be used for social login (configure them in config/services.php).
     */
    public const PROVIDERS = ['google', 'facebook'];

    /**
     * Redirect to the OAuth provider.
     */
    public function index(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider callback, log the user in and send them back to the SPA.
     */
    public function show(Request $request, string $provider): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            Log::warning('Social login failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return $this->redirectWithError('social_failed');
        }

        if (!$socialUser->getEmail()) {
            return $this->redirectWithError('social_no_email');
        }

        $user = User::where('provider', $provider)->where('provider_id', $socialUser->getId())->first();

        if (!$user) {
            // Never attach a social identity to an existing password account automatically: anyone
            // controlling a provider account with that email could otherwise take it over.
            if (User::where('email', $socialUser->getEmail())->exists()) {
                return $this->redirectWithError('social_account_exists');
            }

            [$firstName, $lastName] = array_pad(explode(' ', (string) $socialUser->getName(), 2), 2, null);

            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $socialUser->getEmail(),
                'password' => Str::random(40),
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'status' => UserStatus::Enabled,
                'email_verified_at' => now(),
            ]);

            $user->assign(UserRole::User->value);
        }

        if ($user->isDisabled()) {
            return $this->redirectWithError('account_disabled');
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        $request->session()->forget('two_factor_verified');

        $user->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);

        if ($user->needsTwoFactor()) {
            $user->sendTwoFactorCode($user->two_factor_method_enabled);
        }

        return redirect()->away(url()->app('/dashboard'));
    }

    protected function redirectWithError(string $error): RedirectResponse
    {
        return redirect()->away(url()->app('/?error='.$error));
    }
}
