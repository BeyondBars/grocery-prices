<?php

namespace App\Support\Mixins;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Helpers for building/validating signed links that point at the SPA (JS_APP_URL) rather than
 * at the API itself. Mixed into Illuminate\Routing\UrlGenerator.
 */
class UrlMixin
{
    /**
     * URL to the frontend application with an optional path.
     */
    public function app(): Closure
    {
        return function (?string $path = null): string {
            /** @var \Illuminate\Routing\UrlGenerator $this */
            return rtrim(config('app.js_app_url'), '/').'/'.ltrim((string) $path, '/');
        };
    }

    /**
     * Signed URL to the frontend application.
     */
    public function appSigned(): Closure
    {
        return function (string $path, array $signatureParameters, $expires = null, array $query = []): string {
            /** @var \Illuminate\Routing\UrlGenerator $this */
            $signature = $this->appSignature($signatureParameters, $expires);

            if ($expires) {
                $query['expires'] = $this->availableAt($expires);
            }

            $queryString = count($query) > 0 ? '?'.http_build_query($query) : '';

            return $this->app($path).'/'.$signature.$queryString;
        };
    }

    /**
     * Signature for the given parameters and expiry.
     */
    public function appSignature(): Closure
    {
        return function (array $signatureParameters, $expires = null): string {
            /** @var \Illuminate\Routing\UrlGenerator $this */
            if ($expires) {
                $signatureParameters = $signatureParameters + ['expires' => $this->availableAt($expires)];
            }

            ksort($signatureParameters);

            return hash_hmac('sha256', implode('.', $signatureParameters), $this->resolveAppKey());
        };
    }

    /**
     * Whether the request has a valid (and unexpired) signature for the given parameters.
     */
    public function hasValidAppSignature(): Closure
    {
        return function (Request $request, array $signatureParameters): bool {
            /** @var \Illuminate\Routing\UrlGenerator $this */
            $expires = $request->input('expires');

            if ($expires) {
                $signatureParameters = $signatureParameters + ['expires' => $expires];
            }

            ksort($signatureParameters);

            $signature = hash_hmac('sha256', implode('.', $signatureParameters), $this->resolveAppKey());

            return hash_equals($signature, (string) $request->input('signature'))
                && !($expires && now()->getTimestamp() > (int) $expires);
        };
    }

    /**
     * The key used for signing. Uses the URL generator's key resolver (the app key by default),
     * which also lets tests swap the key with URL::setKeyResolver().
     */
    protected function resolveAppKey(): Closure
    {
        return function (): string {
            /** @var \Illuminate\Routing\UrlGenerator $this */
            $key = $this->keyResolver ? call_user_func($this->keyResolver) : config('app.key');

            return (string) Arr::first(Arr::wrap($key));
        };
    }
}
