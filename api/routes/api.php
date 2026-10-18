<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest / public routes
|--------------------------------------------------------------------------
*/

// Registration step 1: send a signed verification link to the email address.
Route::post('/verification', Controllers\EmailVerificationController::class)->middleware('throttle:emails');
// Registration step 2: validate the signed link the user clicked.
Route::get('/verify', Controllers\ValidateSignatureController::class);
// Registration step 3: create the account (requires a verified signature from step 2).
Route::post('/account-setup', [Controllers\AccountSetupController::class, 'store']);

// Session based login / logout (Sanctum SPA authentication).
Route::post('/auth', [Controllers\AuthController::class, 'store']);
Route::delete('/auth', [Controllers\AuthController::class, 'destroy']);

// Verify an existing user's email (or a changed email) from the signed link.
Route::post('/auth/verification', [Controllers\Auth\VerificationController::class, 'store']);

Route::post('/password/forgot', [Controllers\Auth\ForgotPasswordController::class, 'store'])->middleware('throttle:emails');
Route::post('/password/reset', [Controllers\Auth\ResetPasswordController::class, 'store']);

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {
    // Available before the 2FA login challenge has been completed.
    Route::get('/user', Controllers\Auth\UserController::class);
    Route::post('/two-factor/sms', Controllers\TwoFactor\SmsController::class)->middleware('throttle:two-factor-send');
    Route::post('/two-factor/email', Controllers\TwoFactor\EmailController::class)->middleware('throttle:two-factor-send');
    Route::post('/two-factor/verify', Controllers\TwoFactor\VerifyController::class)->middleware('throttle:two-factor-verify');

    // Everything below requires the 2FA challenge to be passed (when the user has 2FA enabled).
    Route::middleware(['2fa'])->group(function () {
        Route::put('/two-factor', Controllers\TwoFactor\UpdateController::class);
        Route::put('/accept-terms', Controllers\AcceptTermsController::class);
        Route::post('/auth/verification/resend', [Controllers\Auth\ResendVerificationController::class, 'store'])->middleware('throttle:two-factor-send');

        // Users
        Route::get('/users', [Controllers\UserController::class, 'index']);
        Route::post('/users', [Controllers\UserController::class, 'store']);
        Route::get('/users/{user}', [Controllers\UserController::class, 'show']);
        Route::put('/users/{user}', [Controllers\UserController::class, 'update']);
        Route::delete('/users/{user}', [Controllers\UserController::class, 'destroy']);
        Route::put('/users/{user}/password', [Controllers\User\PasswordController::class, 'update']);
        Route::put('/users/{user}/disable-two-factor', Controllers\User\DisableTwoFactorController::class);

        // Roles (read-only: roles are defined in code and synced with `php artisan abilities:refresh`)
        Route::get('/roles', [Controllers\RoleController::class, 'index']);
        Route::get('/roles/{role}', [Controllers\RoleController::class, 'show']);

        // Email templates (registered in App\Providers\EmailTemplateServiceProvider)
        Route::get('/email-templates', [Controllers\EmailTemplateController::class, 'index']);
        Route::get('/email-templates/{template}', [Controllers\EmailTemplateController::class, 'show']);
        Route::post('/email-templates/{template}/test', [Controllers\EmailTemplateController::class, 'sendTest'])->middleware('throttle:test-emails');

        // Catalog (shared by all users; see the catalog policies for who can edit what)
        Route::apiResource('stores', Controllers\StoreController::class);
        Route::apiResource('brands', Controllers\BrandController::class)->except('show');
        Route::apiResource('categories', Controllers\CategoryController::class)->except('show');
        Route::apiResource('products', Controllers\ProductController::class);
        Route::post('/products/{product}/merge', [Controllers\ProductController::class, 'merge']);
        Route::get('/products/{product}/prices', [Controllers\ProductController::class, 'prices']);
        Route::get('/products/{product}/price-history', [Controllers\ProductController::class, 'priceHistory']);

        // Receipts (private to the user who uploaded them)
        Route::post('/receipts', [Controllers\ReceiptController::class, 'store'])->middleware('throttle:receipt-uploads');
        Route::apiResource('receipts', Controllers\ReceiptController::class)->except('store');
        Route::get('/receipts/{receipt}/file', [Controllers\ReceiptController::class, 'file']);
        Route::post('/receipts/{receipt}/reprocess', [Controllers\ReceiptController::class, 'reprocess'])->middleware('throttle:receipt-uploads');
        Route::put('/receipt-items/{receiptItem}', [Controllers\ReceiptItemController::class, 'update']);
        Route::get('/receipt-items/{receiptItem}/candidates', [Controllers\ReceiptItemController::class, 'candidates']);
        Route::post('/receipt-items/{receiptItem}/match', [Controllers\ReceiptItemController::class, 'match']);
    });
});
