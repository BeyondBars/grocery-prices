<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

define('LARAVEL_START', microtime(true));

function set_cors_headers($callback)
{
    $headers = [
        'Access-Control-Allow-Origin' => request()->header('Origin') ?? '*',
        'Access-Control-Allow-Credentials' => config('cors.supports_credentials'),
        'Access-Control-Allow-Methods' => config('cors.allowed_methods'),
        'Access-Control-Allow-Headers' => config('cors.allowed_headers'),
        'Access-Control-Expose-Headers' => config('cors.exposed_headers')
    ];

    foreach ($headers as $key => $value) {
        if (is_array($value)) {
            $callback($key, implode(', ', $value));
        } elseif (is_bool($value)) {
            if ($value === true) {
                $callback($key, 'true');
            }
        } else {
            $callback($key, $value);
        }
    }
}

if (! function_exists('dd')) {
    /**
     * Dump the passed variables and end the script.
     *
     * @param  mixed  $args
     * @return void
     */
    function dd(...$args)
    {
        set_cors_headers(function ($header, $value) {
            header(sprintf('%s: %s', $header, $value));
        });

        http_response_code(500);

        foreach ($args as $x) {
            (new Symfony\Component\VarDumper\VarDumper)->dump($x);
        }

        die(1);
    }
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| our application. We just need to utilize it! We'll simply require it
| into the script here so that we don't have to worry about manual
| loading any of our classes later on. It feels great to relax.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Turn On The Lights
|--------------------------------------------------------------------------
|
| We need to illuminate PHP development, so let us turn on the lights.
| This bootstraps the framework and gets it ready for use, then it
| will load up this application so that we can run it and send
| the responses back to the browser and delight our users.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request
| through the kernel, and send the associated response back to
| the client's browser allowing them to enjoy the creative
| and wonderful application we have prepared for them.
|
*/

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

if ($app->environment('local', 'testing')) {
    set_cors_headers(function ($header, $value) use ($response) {
        $response->headers->set($header, $value);
    });
}

$response->send();

$kernel->terminate($request, $response);
