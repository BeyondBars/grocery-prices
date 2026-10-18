<?php

namespace App\Matching;

use App\Models\Brand;
use App\Support\Size;

/**
 * A receipt line or product name broken into what matching compares.
 */
final readonly class NormalisedLine
{
    /**
     * @param  string  $text  upper case, abbreviations expanded
     * @param  string[]  $tokens  words that describe the product (no brand, size, pack or numbers)
     * @param  string[]  $markers  variant markers found, e.g. ["FULL CREAM"]
     * @param  string  $name  the words without brand, size or pack count, e.g. "FULL CREAM MILK"
     */
    public function __construct(
        public string $text,
        public array $tokens,
        public array $markers,
        public ?Size $size,
        public ?int $packCount,
        public ?Brand $brand,
        public string $name = '',
    ) {
    }
}
