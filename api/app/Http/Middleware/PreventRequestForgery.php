<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

/**
 * CSRF protection (Laravel 13's replacement for VerifyCsrfToken).
 *
 * Requests from the same origin pass on the Sec-Fetch-Site header alone. The SPA runs on another
 * port (same-site, not same-origin), so its requests are checked against the XSRF-TOKEN cookie
 * that axios echoes back in the X-XSRF-TOKEN header.
 */
class PreventRequestForgery extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
