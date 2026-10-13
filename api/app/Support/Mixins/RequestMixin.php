<?php

namespace App\Support\Mixins;

use Closure;
use Illuminate\Support\Facades\URL;

class RequestMixin
{
    /**
     * Abort with 403 unless the request carries a valid application signature.
     */
    public function validateAppSignature(): Closure
    {
        return function (array $signatureParameters, string $message = 'Invalid signature.') {
            /** @var \Illuminate\Http\Request $this */
            abort_unless(URL::hasValidAppSignature($this, $signatureParameters), 403, $message);
        };
    }

    /**
     * Whether the 2FA challenge has been passed in the current session.
     */
    public function isTwoFactorVerified(): Closure
    {
        return function (): bool {
            /** @var \Illuminate\Http\Request $this */
            return $this->hasSession() && $this->session()->get('two_factor_verified', false) === true;
        };
    }

    /**
     * Whether this request is authenticated through Sanctum's stateful (SPA) middleware.
     */
    public function isUsingSanctum(): Closure
    {
        return function (): bool {
            /** @var \Illuminate\Http\Request $this */
            return $this->attributes->get('sanctum') === true;
        };
    }

    /**
     * Whether the request carries a valid application signature for the given parameters.
     */
    public function hasValidAppSignature(): Closure
    {
        return function (array $signatureParameters): bool {
            /** @var \Illuminate\Http\Request $this */
            return URL::hasValidAppSignature($this, $signatureParameters);
        };
    }
}
