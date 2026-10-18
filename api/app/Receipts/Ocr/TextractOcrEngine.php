<?php

namespace App\Receipts\Ocr;

use App\Receipts\Parsing\ParsedItem;
use App\Receipts\ReceiptProcessingException;
use App\Support\Enums\ItemUnit;
use App\Support\Money;
use Aws\Textract\Exception\TextractException;
use Aws\Textract\TextractClient;

/**
 * AWS Textract AnalyzeExpense: OCR built for receipts, returning line items as well as text.
 * The synchronous API takes JPEG, PNG or single-page PDFs up to 10 MB.
 */
class TextractOcrEngine implements OcrEngine
{
    public function __construct(private TextractClient $client)
    {
    }

    public function read(string $contents, string $mimeType): OcrResult
    {
        try {
            $result = $this->client->analyzeExpense(['Document' => ['Bytes' => $contents]]);
        } catch (TextractException $e) {
            // Bad documents won't succeed on a retry; anything else (throttling, outages) should.
            if (in_array($e->getAwsErrorCode(), ['UnsupportedDocumentException', 'BadDocumentException', 'DocumentTooLargeException'], true)) {
                throw new ReceiptProcessingException('This file could not be read. Try a clearer photo, or a single-page PDF.', previous: $e);
            }

            throw $e;
        }

        $document = $result['ExpenseDocuments'][0] ?? [];

        return new OcrResult('textract', $this->text($document), $this->items($document));
    }

    private function text(array $document): string
    {
        return collect($document['Blocks'] ?? [])
            ->where('BlockType', 'LINE')
            ->pluck('Text')
            ->implode("\n");
    }

    /**
     * @return ParsedItem[]
     */
    private function items(array $document): array
    {
        $items = [];

        foreach ($document['LineItemGroups'] ?? [] as $group) {
            foreach ($group['LineItems'] ?? [] as $lineItem) {
                $fields = collect($lineItem['LineItemExpenseFields'] ?? [])
                    ->mapWithKeys(fn ($field) => [$field['Type']['Text'] => trim($field['ValueDetection']['Text'] ?? '')]);

                if ($item = $this->item($fields->all())) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    private function item(array $fields): ?ParsedItem
    {
        $total = Money::toCents($fields['PRICE'] ?? null);
        $name = $fields['ITEM'] ?? $fields['EXPENSE_ROW'] ?? '';

        if ($total === null || $name === '') {
            return null;
        }

        $quantityText = strtoupper($fields['QUANTITY'] ?? '');
        $quantity = preg_match('/\d+(?:\.\d+)?/', $quantityText, $match) ? (float) $match[0] : 1.0;

        return new ParsedItem(
            name: $name,
            totalPriceCents: $total,
            quantity: $quantity > 0 ? $quantity : 1.0,
            unit: str_contains($quantityText, 'KG') ? ItemUnit::Kilogram : ItemUnit::Each,
            unitPriceCents: Money::toCents($fields['UNIT_PRICE'] ?? null),
            sku: ($fields['PRODUCT_CODE'] ?? null) ?: null,
            raw: $fields['EXPENSE_ROW'] ?? $name,
        );
    }
}
