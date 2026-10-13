<?php

namespace App\Http\Controllers\TwoFactor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailController extends Controller
{
    /**
     * Email a 2FA code to the logged in user.
     */
    public function __invoke(Request $request): Response
    {
        $request->user()->sendTwoFactorCode('email');

        return response()->noContent();
    }
}
