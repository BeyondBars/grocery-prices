<?php

namespace App\Matching;

use App\Models\ReceiptItem;

interface ProductMatcher
{
    /**
     * Possible products for a receipt line, best first.
     *
     * @return MatchCandidate[]
     */
    public function candidates(ReceiptItem $item, int $limit = 5): array;
}
