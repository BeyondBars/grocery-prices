<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResendVerificationController extends Controller
{
    /**
     * Resend the verification email for the logged in user (to the pending new email if any).
     */
    public function store(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->new_email) {
            $user->sendEmailVerificationNotification($user->new_email);

            return response()->noContent();
        }

        abort_if($user->hasVerifiedEmail(), 400, __('app.email_already_verified'));

        $user->sendEmailVerificationNotification();

        return response()->noContent();
    }
}
