<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\Auth\SuccessfulPasswordResetNotification;
use App\Support\Enums\UserStatus;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    /**
     * Reset the password using the emailed token. Also used to activate invited users.
     */
    public function store(ResetPasswordRequest $request): Response
    {
        $response = Password::broker()->reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->setRememberToken(null);

                // Using the emailed link proves ownership of the address (this is how invited users,
                // created by an admin, activate their account).
                if (!$user->hasVerifiedEmail()) {
                    $user->email_verified_at = now();
                }

                if ($user->status === UserStatus::Pending) {
                    $user->status = UserStatus::Enabled;
                }

                $user->save();
            }
        );

        if ($response !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['generic' => [__($response)]]);
        }

        User::where('email', $request->input('email'))->first()?->notify(new SuccessfulPasswordResetNotification);

        return response()->noContent();
    }
}
