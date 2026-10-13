<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValidateSignatureRequest;
use App\Models\EmailSignature;
use App\Models\User;
use App\Services\VerificationService;

class ValidateSignatureController extends Controller
{
    /**
     * Registration step 2: validate the signed link and mark the email as verified.
     */
    public function __invoke(ValidateSignatureRequest $request)
    {
        $email = $request->query('email');
        $expires = (string) $request->query('expires');
        $signature = (string) $request->query('signature');

        abort_if(User::where('email', $email)->exists(), 400, __('app.account_exists'));

        abort_unless(VerificationService::validateSignature($email, $expires, $signature), 400, __('app.email_signature_invalid'));

        $existingSignature = EmailSignature::where('email', $email)
            ->where('expires_at', $expires)
            ->where('signature', $signature)
            ->first();

        abort_if(!$existingSignature, 400, __('app.email_signature_not_found'));

        if ($existingSignature->verified_at) {
            return response()->json(['message' => __('app.email_already_verified')]);
        }

        $existingSignature->update(['verified_at' => now()]);

        return response()->noContent();
    }
}
