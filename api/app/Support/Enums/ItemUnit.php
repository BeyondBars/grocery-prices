<?php

namespace App\Support\Enums;

/**
 * How a receipt line was sold: by the item, or weighed (price per kg).
 */
enum ItemUnit: string
{
    case Each = 'each';
    case Kilogram = 'kg';
}
