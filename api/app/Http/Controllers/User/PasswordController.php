<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Change the logged in user's own password.
     */
    public function update(UpdatePasswordRequest $request, User $user): Response
    {
        if (!Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Current password was incorrect.']]);
        }

        $user->update(['password' => $request->input('password')]);

        return response()->noContent();
    }
}
