<?php

namespace App\Support;

final class Money
{
    /**
     * "$4.50" => 450, "-1.00" => -100, "1.00-" => -100 (some receipts print the minus last).
     * Returns null when there is no amount in the text.
     */
    public static function toCents(?string $text): ?int
    {
        if (!$text || !preg_match('/(-)?\s*\$?\s*(\d{1,6})[.,](\d{2})\s*(-)?/', $text, $match)) {
            return null;
        }

        $cents = (int) $match[2] * 100 + (int) $match[3];

        return ($match[1] ?? '') === '-' || ($match[4] ?? '') === '-' ? -$cents : $cents;
    }
}
