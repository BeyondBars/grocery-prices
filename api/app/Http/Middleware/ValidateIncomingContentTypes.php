<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class ValidateIncomingContentTypes
{
    /**
     * URIs to not validate the content type.
     *
     * @var array
     */
    protected $except = [
        // Add multipart/file-upload endpoints here, e.g. 'invoices/*/attachments'.
    ];

    /**
     * URIs mappings with the response types they accept.
     *
     * @var array
     */
    protected $accepts = [
        '*' => 'application/json',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!$this->inExceptArray($request)) {
            // Request content type must be JSON if method is POST or PUT.
            if (in_array($request->method(), ['POST', 'PUT']) && !$request->isJson()) {
                throw new UnsupportedMediaTypeHttpException('Unsupported or undefined media type given as content.');
            }

            // Request must indicate that it accepts a valid response type.
            $accepts = Arr::first($this->accepts, function ($accepts, $uri) use ($request) {
                $uri = $uri !== '/' ? trim($uri, '/') : $uri;

                return $request->fullUrlIs($uri) || $request->is($uri);
            }, 'application/json');


            if (!$request->accepts($accepts)) {
                throw new NotAcceptableHttpException('Requested response type is not acceptable.');
            }
        }

        return $next($request);
    }

    /**
     * Determine if the request has a URI that should pass through content type validation.
     *
     * @param  \Illuminate\Http\Request $request
     * @return bool
     */
    protected function inExceptArray($request): bool
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->fullUrlIs($except) || $request->is($except)) {
                return true;
            }
        }

        return false;
    }
}
