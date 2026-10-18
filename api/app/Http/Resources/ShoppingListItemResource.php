<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ShoppingListItem */
class ShoppingListItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            'free_text' => $this->free_text,
            'quantity' => $this->quantity,
            'is_checked' => $this->is_checked,
            'position' => $this->position,
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
