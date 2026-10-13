<?php

namespace App\Services;

use App\Models\EmailSignature;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class VerificationService
{
    protected static function getSecretKey()
    {
        return config('app.key');
    }

    public static function generateSignedUrl(string $path, string $parameter, $expires = null, array $query = []): string
    {
        if ($expires) {
            $query['expires'] = now()->addMinutes($expires)->timestamp;
            $expires = $query['expires'];
        }

        if (count($query) > 0) {
            $query = sprintf('?%s', http_build_query($query));
        } else {
            $query = null;
        }

        $signature = hash_hmac('sha256', "{$parameter}|{$expires}", self::getSecretKey());

        EmailSignature::create([
            'signature' => $signature,
            'expires_at' => $expires,
            'verified_at' => null,
            'email' => $parameter
        ]);

        return rtrim(config('app.js_app_url'), '/') . '/' . ltrim($path, '/').'/'.$signature.$query;
    }

    public static function validateSignature(string $email, string $expires, string $signature): bool
    {
        if ((int) $expires < now()->timestamp) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', "{$email}|{$expires}", self::getSecretKey());

        return hash_equals($expectedSignature, $signature);
    }
}
