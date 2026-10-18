<?php

namespace App\Support;

use App\Support\Enums\SizeUnit;

/**
 * A product size in base units. Parses the ways sizes are written on labels and receipts:
 * "2L", "2 LT", "1.25L", "700G", "1KG", "500ML", "6 EACH".
 */
final readonly class Size
{
    private const UNITS = [
        'ML' => [SizeUnit::Millilitres, 1],
        'L' => [SizeUnit::Millilitres, 1000],
        'LT' => [SizeUnit::Millilitres, 1000],
        'LTR' => [SizeUnit::Millilitres, 1000],
        'LITRE' => [SizeUnit::Millilitres, 1000],
        'LITRES' => [SizeUnit::Millilitres, 1000],
        'G' => [SizeUnit::Grams, 1],
        'GM' => [SizeUnit::Grams, 1],
        'GRAM' => [SizeUnit::Grams, 1],
        'GRAMS' => [SizeUnit::Grams, 1],
        'KG' => [SizeUnit::Grams, 1000],
        'EA' => [SizeUnit::Each, 1],
        'EACH' => [SizeUnit::Each, 1],
    ];

    public function __construct(public float $amount, public SizeUnit $unit)
    {
    }

    public static function parse(?string $text): ?self
    {
        if (!$text || !preg_match(self::pattern(), $text, $match)) {
            return null;
        }

        [$unit, $multiplier] = self::UNITS[strtoupper($match[2])];

        return new self(round((float) $match[1] * $multiplier, 3), $unit);
    }

    /**
     * The text with its size removed: "PAULS MILK 2 LT" => "PAULS MILK ".
     */
    public static function strip(string $text): string
    {
        return preg_replace(self::pattern(), ' ', $text, 1);
    }

    /**
     * The text with its size written one way: "PAULS MILK 2 LTR" => "PAULS MILK 2L", so the same
     * product is remembered under one wording however the receipt spelled the size.
     */
    public static function standardise(string $text): string
    {
        $size = self::parse($text);

        return $size ? trim(preg_replace('/\s+/', ' ', preg_replace(self::pattern(), ' '.strtoupper($size->label()).' ', $text, 1))) : $text;
    }

    private static function pattern(): string
    {
        return '/(\d+(?:\.\d+)?)\s*('.implode('|', array_keys(self::UNITS)).')\b/i';
    }

    /**
     * Human friendly label: 2000 ml => "2L", 700 g => "700g", 1250 ml => "1.25L".
     */
    public function label(): string
    {
        return match ($this->unit) {
            SizeUnit::Millilitres => $this->amount >= 1000 ? self::number($this->amount / 1000).'L' : self::number($this->amount).'ml',
            SizeUnit::Grams => $this->amount >= 1000 ? self::number($this->amount / 1000).'kg' : self::number($this->amount).'g',
            SizeUnit::Each => self::number($this->amount).' each',
        };
    }

    public function equals(?self $other): bool
    {
        return $other !== null && $this->unit === $other->unit && abs($this->amount - $other->amount) < 0.001;
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
