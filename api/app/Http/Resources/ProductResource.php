<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'variant' => $this->variant,
            'size' => $this->size()?->label(),
            'size_amount' => $this->size_amount,
            'size_unit' => $this->size_unit?->value,
            'pack_count' => $this->pack_count,
            'barcode' => $this->barcode,
            'merged_into_id' => $this->merged_into_id,
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            // Only on the product list: the cheapest latest price this user has seen.
            'cheapest_price' => $this->when(array_key_exists('cheapest_price', $this->getAttributes()), fn () => $this->cheapest_price),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'merge' => $request->user()?->can('merge', $this->resource) ?? false,
            ],
        ];
    }
}
