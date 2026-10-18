<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ShoppingList */
class ShoppingListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'items_count' => $this->whenCounted('items'),
            'unchecked_count' => $this->when(isset($this->unchecked_count), fn () => (int) $this->unchecked_count),
            'items' => ShoppingListItemResource::collection($this->whenLoaded('items')),
            'updated_at' => $this->updated_at,
        ];
    }
}
