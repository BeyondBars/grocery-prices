<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyTwoFactor
{
    /**
     * Block the request when the user has 2FA enabled but hasn't passed the challenge this session.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->needsTwoFactor() && !$request->isTwoFactorVerified()) {
            return response()->json([
                'message' => __('auth.two_factor_required'),
                'code' => 'two_factor_required',
            ], 403);
        }

        return $next($request);
    }
}
