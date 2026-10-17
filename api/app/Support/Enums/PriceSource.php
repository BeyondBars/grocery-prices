<?php

namespace App\Support\Enums;

enum PriceSource: string
{
    case Receipt = 'receipt';
    case Online = 'online';
    case Promotion = 'promotion';
    case Manual = 'manual';
}
