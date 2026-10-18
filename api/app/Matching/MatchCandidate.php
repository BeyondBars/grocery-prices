<?php

namespace App\Matching;

use App\Models\Product;

final readonly class MatchCandidate
{
    /**
     * @param  int  $confidence  0-100
     * @param  string  $reason  short explanation for the review screen, e.g. "Same size and brand"
     */
    public function __construct(public Product $product, public int $confidence, public string $reason)
    {
    }
}
