<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Receipt */
class ReceiptResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'store' => new StoreResource($this->whenLoaded('store')),
            'store_location' => $this->store_location,
            'purchased_at' => $this->purchased_at,
            'receipt_number' => $this->receipt_number,
            'subtotal_cents' => $this->subtotal_cents,
            'discount_cents' => $this->discount_cents,
            'total_cents' => $this->total_cents,
            'currency' => $this->currency,
            'failure_reason' => $this->failure_reason,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
            'items_count' => $this->whenCounted('items'),
            'items' => ReceiptItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
