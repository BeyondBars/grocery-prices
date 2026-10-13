<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AddContentTypeEncoding
{
    /**
     * Explicitly set the content type on the request to meet OWASP
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

        if ($request->accepts('text/csv')) {
            $response->header('Content-Type', 'text/csv; charset=UTF-8');
        } elseif ($request->accepts('application/json')) {
            $response->header('Content-Type', 'application/json; charset=UTF-8');
        }

        return $response;
    }
}
