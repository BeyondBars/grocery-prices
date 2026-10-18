<?php

namespace App\Receipts\Parsing;

use App\Receipts\Ocr\OcrResult;
use App\Support\Enums\ItemUnit;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Rule-based parser for Australian supermarket receipts. Reads the header (ABN, date, receipt
 * number) and totals from the text, and the items too unless the OCR engine already found them.
 *
 * Item lines look like "PAULS FULL CREAM MILK 2L      4.50". It also understands the lines that
 * follow an item: quantities ("2 @ $2.50 EACH"), weights ("0.756 kg NET @ $4.90/kg  3.70") and
 * discounts ("PRICE REDUCED  -1.00"), which are folded into the item above.
 */
class TextReceiptParser implements ReceiptParser
{
    /** An amount at the end of a line, maybe with a GST flag ("4.50", "-1.00", "1.00-", "$3.50 #"). It must stand alone, so the "1.25" in "COCA COLA 1.25L" is never a price. */
    private const AMOUNT_AT_END = '/^(?<text>.*?)(?:^|\s+)(?<amount>-?\$?\d{1,5}\.\d{2}-?)(?:\s+[#*^A-Z])?$/';

    private const WEIGHED = '/(?<qty>\d+(?:\.\d+)?)\s*KG\b.*?@\s*\$?(?<rate>\d+\.\d{2})\s*\/\s*KG/';

    private const QUANTITY = '/^(?:QTY\s*)?(?<qty>\d{1,3})\s*(?:@|X)\s*\$?(?<each>\d+\.\d{2})(?:\s*EA(?:CH)?)?/';

    private const DISCOUNT_WORDS = '/\b(PRICE REDUCED|REDUCED|DISCOUNT|PROMO|PROMOTION|SPECIAL|SAVE|LESS|MULTI ?BUY|COUPON|MARKDOWN|OFF)\b/';

    /** Lines with an amount that are about payment, tax or loyalty, never a product. */
    private const NOT_AN_ITEM = '/\b(GST|EFTPOS|CHANGE|CASH|VISA|MASTERCARD|AMEX|DEBIT|CREDIT|ROUNDING|REWARDS?|POINTS|SAVINGS|TENDERED|APPROVED|AUTH|ACCOUNT|BALANCE|PAYMENT|PAID|TAX INVOICE|ABN|PH|TEL|PHONE)\b/';

    private const SUBTOTAL = '/^SUB\s*-?\s*TOTAL\b/';

    private const TOTAL = '/^(TOTAL|BALANCE DUE|AMOUNT DUE)\b(?!.*\b(SAVINGS?|GST|TAX|ITEMS?|DISCOUNTS?)\b)/';

    public function parse(OcrResult $ocr): ParsedReceipt
    {
        $lines = $this->lines($ocr->text);
        $receipt = new ParsedReceipt(
            abn: $this->abn($lines),
            purchasedAt: $this->purchasedAt($lines),
            receiptNumber: $this->receiptNumber($lines),
        );

        $items = $this->readItemsAndTotals($lines, $receipt);

        // An engine that understands receipts (Textract) is better at finding items than our rules.
        if ($ocr->items) {
            $items = $ocr->items;
        }

        [$receipt->items, $receipt->discountCents] = $this->foldDiscounts($items);
        $receipt->warnings = $this->warnings($receipt);

        return $receipt;
    }

    /**
     * @return string[] upper-cased, trimmed, single-spaced, non-empty lines
     */
    private function lines(string $text): array
    {
        return collect(preg_split('/\R/', $text))
            ->map(fn ($line) => Str::of($line)->upper()->squish()->value())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return ParsedItem[] items in receipt order; discount lines have a negative total
     */
    private function readItemsAndTotals(array $lines, ParsedReceipt $receipt): array
    {
        $items = [];
        // A name printed on its own line, waiting for the quantity or weight line that prices it.
        $pendingName = null;

        foreach ($lines as $line) {
            preg_match(self::AMOUNT_AT_END, $line, $amountMatch);
            $amount = isset($amountMatch['amount']) ? Money::toCents($amountMatch['amount']) : null;
            $text = trim($amountMatch['text'] ?? $line);

            if ($amount !== null && preg_match(self::SUBTOTAL, $line)) {
                $receipt->subtotalCents = $amount;
                continue;
            }

            if ($amount !== null && preg_match(self::TOTAL, $line)) {
                $receipt->totalCents = $amount;
                break; // Payment details follow the total.
            }

            if (preg_match(self::WEIGHED, $line, $match)) {
                $name = trim(preg_replace('/\bNET\b/', '', Str::before($text, $match['qty']))) ?: $pendingName;

                if ($name && $amount !== null) {
                    $items[] = new ParsedItem($name, $amount, (float) $match['qty'], ItemUnit::Kilogram, Money::toCents($match['rate']), raw: $line);
                }

                $pendingName = null;
                continue;
            }

            if (preg_match(self::QUANTITY, $line, $match)) {
                $this->applyQuantity($items, $pendingName, $line, $match);
                $pendingName = null;
                continue;
            }

            if (preg_match(self::NOT_AN_ITEM, $line)) {
                $pendingName = null;
                continue;
            }

            if ($amount !== null && ($amount < 0 || preg_match(self::DISCOUNT_WORDS, $text))) {
                $items[] = new ParsedItem($text ?: 'DISCOUNT', -abs($amount), raw: $line);
                continue;
            }

            if ($amount !== null && preg_match('/[A-Z].*[A-Z]/', $text)) {
                $items[] = $this->item($text, $amount, $line);
                $pendingName = null;
                continue;
            }

            if ($amount === null && preg_match('/[A-Z].*[A-Z].*[A-Z]/', $line)) {
                $pendingName = $line;
            }
        }

        return $items;
    }

    /**
     * "2 @ $2.50 EACH  5.00" either prices the name on the line above, or (without its own
     * total) adds the quantity to the item above.
     */
    private function applyQuantity(array &$items, ?string $pendingName, string $line, array $match): void
    {
        $rest = substr($line, strlen($match[0]));
        $total = preg_match('/(-?\d+\.\d{2}-?)/', $rest, $totalMatch) ? Money::toCents($totalMatch[1]) : null;
        $quantity = (float) $match['qty'];
        $each = Money::toCents($match['each']);

        if ($pendingName && $total !== null) {
            $items[] = new ParsedItem($pendingName, $total, $quantity, ItemUnit::Each, $each, raw: $line);

            return;
        }

        $last = end($items);

        if ($last && $last->totalPriceCents > 0) {
            $last->quantity = $quantity;
            $last->unitPriceCents = $each;
        }
    }

    private function item(string $text, int $amount, string $line): ParsedItem
    {
        // Some receipts print the product code first: "9300633603158 PAULS MILK 2L".
        $sku = preg_match('/^(\d{6,14})\s+(.+)$/', $text, $match) ? $match[1] : null;

        return new ParsedItem($sku ? $match[2] : $text, $amount, unitPriceCents: $amount, sku: $sku, raw: $line);
    }

    /**
     * Turn discount lines into a discount on the item above them. A discount before any item
     * (or after one that's already free) stays on the receipt as a whole.
     *
     * @param  ParsedItem[]  $lines
     * @return array{0: ParsedItem[], 1: int}
     */
    private function foldDiscounts(array $lines): array
    {
        $items = [];
        $receiptDiscount = 0;

        foreach ($lines as $line) {
            if ($line->totalPriceCents >= 0) {
                $items[] = $line;
                continue;
            }

            $last = end($items);
            $discount = abs($line->totalPriceCents);

            if ($last && $last->discountCents + $discount <= $last->totalPriceCents) {
                $last->discountCents += $discount;
            } else {
                $receiptDiscount += $discount;
            }
        }

        return [$items, $receiptDiscount];
    }

    private function warnings(ParsedReceipt $receipt): array
    {
        if (!$receipt->items) {
            return ['No items could be read from this receipt.'];
        }

        // A few cents either way is cash rounding.
        if ($receipt->totalCents !== null && abs($receipt->itemsTotalCents() - $receipt->totalCents) > 5) {
            return [sprintf(
                'The items add up to $%s but the receipt total is $%s. Check for missed or misread lines.',
                number_format($receipt->itemsTotalCents() / 100, 2),
                number_format($receipt->totalCents / 100, 2),
            )];
        }

        return [];
    }

    private function abn(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/\bABN\W*((?:\d\s?){11})/', $line, $match)) {
                return preg_replace('/\D/', '', $match[1]);
            }
        }

        return null;
    }

    /**
     * The first valid date (day first, as in Australia) and the time on the same line, if any.
     */
    private function purchasedAt(array $lines): ?CarbonImmutable
    {
        $months = 'JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC';

        foreach ($lines as $line) {
            if (preg_match('/\b(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4}|\d{2})\b/', $line, $match)) {
                [$day, $month, $year] = [(int) $match[1], (int) $match[2], (int) $match[3]];
            } elseif (preg_match("/\b(\d{1,2})[ -]?($months)[A-Z]*[ -]?(\d{4}|\d{2})\b/", $line, $match)) {
                [$day, $month, $year] = [(int) $match[1], array_search($match[2], explode('|', $months)) + 1, (int) $match[3]];
            } else {
                continue;
            }

            $year += $year < 100 ? 2000 : 0;

            if (!checkdate($month, $day, $year)) {
                continue;
            }

            $date = CarbonImmutable::create($year, $month, $day);

            if (preg_match('/\b(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?\b/', $line, $time)) {
                $hour = (int) $time[1] % 12 + (($time[3] ?? '') === 'PM' ? 12 : 0);
                $date = $date->setTime(($time[3] ?? '') ? $hour : (int) $time[1], (int) $time[2]);
            }

            if ($date->lessThanOrEqualTo(now()->addDay()) && $date->year >= 2000) {
                return $date;
            }
        }

        return null;
    }

    private function receiptNumber(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/\b(?:RECEIPT|TRANS(?:ACTION)?|INVOICE|DOCKET|REF)\s*(?:NO\.?|NUMBER|#|ID)?\s*[:#]?\s*([A-Z0-9\/-]*\d[A-Z0-9\/-]*)/', $line, $match)) {
                return $match[1];
            }
        }

        return null;
    }
}
