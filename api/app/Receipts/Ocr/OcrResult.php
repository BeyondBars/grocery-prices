<?php

namespace App\Receipts\Ocr;

use App\Models\Receipt;
use App\Receipts\Parsing\ParsedItem;

/**
 * What an OCR engine read: the receipt's text, plus line items when the engine understands
 * receipts itself (Textract AnalyzeExpense). Without items, the parser reads them from the text.
 */
class OcrResult
{
    /**
     * @param  ParsedItem[]|null  $items
     */
    public function __construct(
        public string $provider,
        public string $text,
        public ?array $items = null,
    ) {
    }

    /**
     * Rebuild the OCR result saved on a receipt, so it can be re-parsed without paying for OCR again.
     */
    public static function fromReceipt(Receipt $receipt): self
    {
        $items = $receipt->parsed_data['ocr_items'] ?? null;

        return new self(
            $receipt->ocr_provider,
            $receipt->ocr_text,
            $items === null ? null : array_map(ParsedItem::fromArray(...), $items),
        );
    }

    /**
     * The same result with anything that looks like a card number masked: "4111 1111 1111 1111"
     * becomes "************1111". Receipts rarely print full numbers, but we never store one.
     * Only 15-19 digit numbers passing the Luhn check count, so 13-digit barcodes are left alone.
     */
    public function masked(): self
    {
        $text = preg_replace_callback('/\b\d(?:[ -]?\d){14,18}\b/', function ($match) {
            $digits = preg_replace('/\D/', '', $match[0]);

            return self::passesLuhn($digits) ? str_repeat('*', strlen($digits) - 4).substr($digits, -4) : $match[0];
        }, $this->text);

        return new self($this->provider, $text, $this->items);
    }

    private static function passesLuhn(string $digits): bool
    {
        $sum = 0;

        foreach (str_split(strrev($digits)) as $position => $digit) {
            $value = (int) $digit * ($position % 2 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return $sum % 10 === 0;
    }

    public function itemsToArray(): ?array
    {
        return $this->items === null ? null : array_map(fn (ParsedItem $item) => $item->toArray(), $this->items);
    }
}
