<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserListResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Support\Enums\UserStatus;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('list', User::class);

        return UserListResource::collection(
            QueryBuilder::for(User::query()->with('role'))
                ->allowedFilters(
                    AllowedFilter::exact('status'),
                    AllowedFilter::callback('search', function ($query, $value) {
                        $query->where(function ($query) use ($value) {
                            $query->where('first_name', 'like', "%{$value}%")
                                ->orWhere('last_name', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%");
                        });
                    }),
                )
                ->allowedSorts('first_name', 'last_name', 'email', 'created_at', 'last_login_at')
                ->defaultSort('first_name')
                ->paginate(5)
        );
    }

    public function show(User $user): UserResource
    {
        $this->authorize('show', $user);

        return new UserResource($user->load('role'));
    }

    /**
     * Invite a new user: they're created pending and emailed a link to set their password.
     */
    public function store(StoreUserRequest $request): UserResource
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                ...$request->safe()->only(['first_name', 'last_name', 'email', 'mobile']),
                'password' => Str::random(40),
                'status' => UserStatus::Pending,
            ]);

            $user->assign($request->input('role'));

            return $user;
        });

        $user->notify(new ResetPasswordNotification(Password::broker()->createToken($user), invite: true));

        return new UserResource($user->load('role'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->fill($request->safe()->only(['first_name', 'last_name', 'mobile']));

        // Only admins (authorised in the request) can change status/role.
        if ($request->has('status')) {
            $user->status = UserStatus::from($request->input('status'));
        }

        $user->save();

        if ($request->filled('role')) {
            $user->roles()->detach();
            $user->assign($request->input('role'));
        }

        // Email changes must be verified: the new address is held in `new_email` until the link
        // sent to it is clicked (see Auth\VerificationController).
        $email = $request->input('email');

        if ($email && $email !== $user->email && $email !== $user->new_email) {
            $user->update(['new_email' => $email, 'new_email_requested_at' => now()]);
            $user->sendEmailVerificationNotification($email);
        }

        return new UserResource($user->refresh()->load('role'));
    }

    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            $user->abilities()->detach();
            $user->verificationCodes()->delete();
            $user->delete();
        });

        return response()->noContent();
    }
}
