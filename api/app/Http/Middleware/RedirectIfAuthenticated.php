<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Already authenticated.'], 400)
                : redirect()->away(url()->app('/dashboard'));
        }

        return $next($request);
    }
}
