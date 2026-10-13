<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Support\Enums\UserStatus;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Email a password reset link. Always returns 204 so emails can't be enumerated.
     */
    public function store(ForgotPasswordRequest $request): Response
    {
        $user = User::where('email', $request->input('email'))
            ->where('status', '!=', UserStatus::Disabled->value)
            ->first();

        if ($user) {
            $user->notify(new ResetPasswordNotification(Password::broker()->createToken($user)));
        }

        return response()->noContent();
    }
}
