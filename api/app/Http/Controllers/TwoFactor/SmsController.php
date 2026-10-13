<?php

namespace App\Http\Controllers\TwoFactor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SmsController extends Controller
{
    /**
     * SMS a 2FA code to the logged in user's mobile.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->mobile, 422, __('auth.two_factor_no_mobile'));

        $user->sendTwoFactorCode('sms');

        return response()->noContent();
    }
}
