<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'role' => $this->whenLoaded('role', fn () => $this->role?->title),
            'status' => $this->status?->value,
            'last_login_at' => $this->last_login_at,
        ];
    }
}
