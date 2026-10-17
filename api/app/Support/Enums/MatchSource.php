<?php

namespace App\Support\Enums;

/**
 * Who made a product match (or created a product alias).
 */
enum MatchSource: string
{
    case Rule = 'rule';
    case Ai = 'ai';
    case User = 'user';
}
