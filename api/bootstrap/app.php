<?php

use App\Http\Middleware\AddContentTypeEncoding;
use App\Http\Middleware\AddSecureResponseHeaders;
use App\Http\Middleware\CheckForMaintenanceMode;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\MergeFormData;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateIncomingContentTypes;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\VerifyTwoFactor;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global middleware. CORS is global so pre-flight (OPTIONS) requests are answered
        // before routing, which is how Laravel expects it to be configured.
        $middleware->use([
            HandleCors::class,
            CheckForMaintenanceMode::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
            TrustProxies::class,
        ]);

        $middleware->group('web', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
        ]);

        // Sanctum's stateful middleware starts the session (cookies, session, CSRF) for requests
        // coming from the SPA, so StartSession must NOT be added here a second time.
        $middleware->group('api', [
            ForceJsonResponse::class,
            EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            SubstituteBindings::class,
            'cache.headers:no_cache;max_age=0',
            AddSecureResponseHeaders::class,
            AddContentTypeEncoding::class,
            ValidateIncomingContentTypes::class,
        ]);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.basic' => AuthenticateWithBasicAuth::class,
            'bindings' => SubstituteBindings::class,
            'cache.headers' => SetCacheHeaders::class,
            'can' => Authorize::class,
            'guest' => RedirectIfAuthenticated::class,
            'password.confirm' => RequirePassword::class,
            'signed' => ValidateSignature::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
            '2fa' => VerifyTwoFactor::class,
            'uploads' => MergeFormData::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Always render JSON for the API (the SPA never expects HTML error pages).
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->expectsJson() || !$request->is('auth/google*', 'auth/facebook*')
        );
    })->create();
