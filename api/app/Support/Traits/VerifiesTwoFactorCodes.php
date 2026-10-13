<?php

namespace App\Support\Traits;

use App\Exceptions\TwoFactorFailedException;
use App\Models\User;

trait VerifiesTwoFactorCodes
{
    /**
     * Verify a 2FA code for the given method (falls back to the user's enabled method).
     *
     * @throws \App\Exceptions\TwoFactorFailedException
     */
    public function verifyTwoFactorCode(string $code, ?string $method = null): void
    {
        $method = $method ?? $this->two_factor_method_enabled;

        if (!in_array($method, User::TWO_FACTOR_METHODS, true)) {
            throw new TwoFactorFailedException(__('auth.two_factor_no_method'));
        }

        $code = preg_replace('/\s+/', '', $code);

        $valid = $this->verificationCodes()
            ->viaMethod($method)
            ->get()
            ->contains(fn ($verificationCode) => $verificationCode->isNotExpired() && hash_equals($verificationCode->code, $code));

        if (!$valid) {
            throw new TwoFactorFailedException(__('auth.two_factor_failed'));
        }

        // Codes are single use: once one is accepted, remove all outstanding codes.
        $this->verificationCodes()->delete();
    }
}
