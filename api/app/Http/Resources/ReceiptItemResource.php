<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReceiptItem */
class ReceiptItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'line_number' => $this->line_number,
            'original_name' => $this->original_name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit' => $this->unit->value,
            'unit_price_cents' => $this->unit_price_cents,
            'total_price_cents' => $this->total_price_cents,
            'discount_cents' => $this->discount_cents,
            'paid_unit_price_cents' => $this->paidUnitPriceCents(),
            'match_status' => $this->match_status->value,
            'match_confidence' => $this->match_confidence,
            'matched_by' => $this->matched_by?->value,
            'product' => new ProductResource($this->whenLoaded('product')),
            'suggested_product' => new ProductResource($this->whenLoaded('suggestedProduct')),
        ];
    }
}
