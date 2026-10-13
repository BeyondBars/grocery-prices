<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerificationRequest;
use App\Models\EmailSignature;
use App\Notifications\SignupVerifyEmailNotification;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class EmailVerificationController extends Controller
{
    /**
     * Registration step 1: email a signed link (valid 30 minutes) to the given address.
     */
    public function __invoke(VerificationRequest $request): JsonResponse
    {
        $email = $request->input('email');

        // Only the latest link for an address is valid.
        EmailSignature::where('email', $email)->whereNull('verified_at')->delete();

        $signedUrl = VerificationService::generateSignedUrl('/verify', $email, 30, ['email' => $email]);

        Notification::route('mail', $email)->notify(new SignupVerifyEmailNotification($signedUrl));

        return response()->json(['message' => 'Verification email sent']);
    }
}
