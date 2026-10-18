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
            // The shop's local time as printed on the receipt, with no timezone to convert.
            'purchased_at' => $this->purchased_at?->format('Y-m-d H:i'),
            'receipt_number' => $this->receipt_number,
            'subtotal_cents' => $this->subtotal_cents,
            'discount_cents' => $this->discount_cents,
            'total_cents' => $this->total_cents,
            'currency' => $this->currency,
            'failure_reason' => $this->failure_reason,
            // Things to check on the review screen, e.g. items not adding up to the total.
            'warnings' => $this->parsed_data['warnings'] ?? [],
            'file_mime' => $this->file_mime,
            'has_file' => $this->file_path !== null,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
            'items_count' => $this->whenCounted('items'),
            'items' => ReceiptItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
