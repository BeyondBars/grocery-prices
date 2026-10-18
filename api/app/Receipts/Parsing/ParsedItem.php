<?php

namespace App\Receipts\Parsing;

use App\Support\Enums\ItemUnit;

/**
 * One product line read from a receipt. A negative total is a discount line, which the parser
 * folds into the item above it.
 */
class ParsedItem
{
    public function __construct(
        public string $name,
        public int $totalPriceCents,
        public float $quantity = 1,
        public ItemUnit $unit = ItemUnit::Each,
        public ?int $unitPriceCents = null,
        public int $discountCents = 0,
        public ?string $sku = null,
        public ?string $raw = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'total_price_cents' => $this->totalPriceCents,
            'quantity' => $this->quantity,
            'unit' => $this->unit->value,
            'unit_price_cents' => $this->unitPriceCents,
            'discount_cents' => $this->discountCents,
            'sku' => $this->sku,
            'raw' => $this->raw,
        ];
    }

    public static function fromArray(array $item): self
    {
        return new self(
            name: $item['name'],
            totalPriceCents: $item['total_price_cents'],
            quantity: $item['quantity'] ?? 1,
            unit: ItemUnit::tryFrom($item['unit'] ?? '') ?? ItemUnit::Each,
            unitPriceCents: $item['unit_price_cents'] ?? null,
            discountCents: $item['discount_cents'] ?? 0,
            sku: $item['sku'] ?? null,
            raw: $item['raw'] ?? null,
        );
    }
}
