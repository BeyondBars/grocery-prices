<?php

use App\Http\Controllers\SocialiteController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response('', 200));

// Social login (full-page redirects, so these live on the "web" middleware group).
Route::get('auth/{provider}', [SocialiteController::class, 'index'])->whereIn('provider', SocialiteController::PROVIDERS);
Route::get('auth/{provider}/callback', [SocialiteController::class, 'show'])->whereIn('provider', SocialiteController::PROVIDERS);
