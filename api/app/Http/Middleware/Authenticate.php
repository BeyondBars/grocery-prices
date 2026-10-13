<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * The API never redirects; unauthenticated requests get a 401 JSON response.
     */
    protected function redirectTo($request)
    {
        return null;
    }
}
