<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'new_email' => $this->new_email,
            'mobile' => $this->mobile,
            'status' => $this->status?->value,
            'role' => new RoleResource($this->whenLoaded('role')),
            'account_type' => $this->whenLoaded('role', fn () => $this->role?->name),
            'provider' => $this->provider,
            'email_verified' => $this->isEmailVerified(),
            'terms_accepted' => $this->terms_accepted_at !== null,
            'last_login_at' => $this->last_login_at,
            'last_login_ip' => $this->last_login_ip,
            'two_factor_required' => $this->two_factor_required,
            'two_factor_method_enabled' => $this->two_factor_method_enabled,
            'two_factor_method_preferred' => $this->two_factor_method_preferred,
            // Whether the current session has passed the 2FA challenge (only meaningful for "me").
            'two_factor_verified' => $request->user()?->is($this->resource) ? $request->isTwoFactorVerified() : null,
            'abilities' => $this->whenLoaded('abilities', fn () => AbilityResource::collection($this->abilities->where('name', '<>', '*')->values())),
        ];
    }
}
