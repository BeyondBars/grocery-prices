<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountSetupRequest;
use App\Models\EmailSignature;
use App\Models\User;
use App\Support\Enums\UserRole;
use App\Support\Enums\UserStatus;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Response;

class AccountSetupController extends Controller
{
    /**
     * Registration step 3: create the account.
     *
     * The email must have been verified through the signed link from step 1/2, so the account is
     * created enabled and verified, and the user is logged straight in.
     */
    public function store(AccountSetupRequest $request, Auth $auth): Response
    {
        $email = $request->input('email');

        $verified = EmailSignature::where('email', $email)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', now()->subDay())
            ->exists();

        abort_unless($verified, 403, __('app.email_not_verified'));

        $user = User::create([
            ...$request->safe()->only(['first_name', 'last_name', 'mobile', 'email']),
            'password' => $request->input('password'),
            'status' => UserStatus::Enabled,
            'terms_accepted_at' => now(),
            'email_verified_at' => now(),
        ]);

        $user->assign(UserRole::User->value);

        // The signup link has served its purpose.
        EmailSignature::where('email', $email)->delete();

        $auth->guard('web')->login($user);
        $request->session()->regenerate();

        return response()->noContent();
    }
}
