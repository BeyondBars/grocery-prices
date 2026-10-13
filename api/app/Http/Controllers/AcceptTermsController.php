<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class AcceptTermsController extends Controller
{
    /**
     * Record that the logged in user accepted the terms (e.g. users created via social login).
     */
    public function __invoke(Request $request): UserResource
    {
        $request->validate(['agree_terms' => ['accepted']]);

        $user = $request->user();
        $user->update(['terms_accepted_at' => now()]);

        return new UserResource($user->loadAbilities());
    }
}
