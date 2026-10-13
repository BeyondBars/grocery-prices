<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Role */
class RoleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->enum()?->desc(),
            'abilities' => $this->whenLoaded('abilities', fn () => AbilityResource::collection($this->abilities->where('name', '<>', '*')->values())),
        ];
    }
}
