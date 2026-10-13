<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AddSecureResponseHeaders
{
    /**
     * Add secure headers to the response object to meet OWASP.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof StreamedResponse) {
            return $response;
        }

        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'DENY');
        $response->header('Referrer-Policy', 'no-referrer');
        $response->header('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
        $response->header('Content-Disposition', 'attachment; filename="api.json"');
        $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubdomains');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');

        return $response;
    }
}
