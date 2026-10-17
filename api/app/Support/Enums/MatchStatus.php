<?php

namespace App\Support\Enums;

/**
 * Where a receipt item is in the product matching process.
 */
enum MatchStatus: string
{
    case Pending = 'pending';
    case AutoMatched = 'auto_matched';
    case Suggested = 'suggested';
    case Confirmed = 'confirmed';
    case NewProduct = 'new_product';
    case Ignored = 'ignored';

    /**
     * Whether the item is linked to a product we trust enough to record its price.
     */
    public function isMatched(): bool
    {
        return in_array($this, [self::AutoMatched, self::Confirmed, self::NewProduct], true);
    }
}
