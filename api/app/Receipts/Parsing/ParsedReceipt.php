<?php

namespace App\Receipts\Parsing;

use Carbon\CarbonImmutable;

/**
 * Everything the parser could read from a receipt. Any field can be missing; the user fills
 * the gaps on the review screen.
 */
class ParsedReceipt
{
    /**
     * @param  ParsedItem[]  $items
     * @param  string[]  $warnings  Things the user should check, e.g. items not adding up to the total.
     */
    public function __construct(
        public array $items = [],
        public ?string $abn = null,
        public ?CarbonImmutable $purchasedAt = null,
        public ?string $receiptNumber = null,
        public ?int $subtotalCents = null,
        public ?int $totalCents = null,
        public int $discountCents = 0,
        public array $warnings = [],
    ) {
    }

    /**
     * What the items add up to after their own discounts and any receipt-level discount.
     */
    public function itemsTotalCents(): int
    {
        return array_sum(array_map(fn (ParsedItem $item) => $item->totalPriceCents - $item->discountCents, $this->items))
            - $this->discountCents;
    }
}
