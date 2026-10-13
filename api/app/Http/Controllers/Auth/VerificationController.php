<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

class VerificationController extends Controller
{
    /**
     * Verify a user's email (or pending new email) from the signed link.
     *
     * This deliberately does NOT log anyone in: a link in an inbox is not a substitute for the
     * password (+ 2FA) login.
     */
    public function store(Request $request): Response
    {
        $email = base64_decode((string) $request->input('identifier'), true);

        abort_unless($email, 403, Lang::get('app.email_signature_invalid'));

        $user = User::query()
            ->where('new_email', $email)
            ->orWhere('email', $email)
            ->firstOrFail();

        $isNewEmail = $user->new_email !== null && $user->new_email === $email;

        // The signature is bound to the exact address being verified.
        abort_unless(
            $request->hasValidAppSignature($user->emailVerificationSignatureParameters($email)),
            403,
            Lang::get('app.email_signature_invalid')
        );

        if ($isNewEmail) {
            $user->markNewEmailAsVerified();

            return response()->noContent();
        }

        abort_if($user->hasVerifiedEmail(), 400, Lang::get('app.email_already_verified'));

        $user->markEmailAsVerified();

        if ($user->status === UserStatus::Pending) {
            $user->update(['status' => UserStatus::Enabled]);
        }

        return response()->noContent();
    }
}
