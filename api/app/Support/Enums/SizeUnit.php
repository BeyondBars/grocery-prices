<?php

namespace App\Support\Enums;

/**
 * Base unit a product size is stored in. "2L" is stored as 2000 ml, "1.5kg" as 1500 g.
 */
enum SizeUnit: string
{
    case Grams = 'g';
    case Millilitres = 'ml';
    case Each = 'each';
}
