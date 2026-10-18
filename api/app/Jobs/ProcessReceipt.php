<?php

namespace App\Jobs;

use App\Models\Receipt;
use App\Receipts\Ocr\OcrResult;
use App\Receipts\Parsing\ParsedItem;
use App\Receipts\Parsing\ParsedReceipt;
use App\Receipts\Parsing\ReceiptParser;
use App\Receipts\ReceiptProcessingException;
use App\Receipts\ReceiptReader;
use App\Receipts\StoreIdentifier;
use App\Support\Enums\MatchStatus;
use App\Support\Enums\ReceiptStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reads an uploaded receipt: OCR (skipped when the receipt already has OCR text), parse,
 * identify the store, then replace the receipt's items with what was read.
 */
class ProcessReceipt implements ShouldQueue
{
    use Queueable;

    /** Real errors (OCR outages, timeouts) are retried this many times... */
    public int $maxExceptions = 3;

    // Below the database queue's retry_after (90s), so a slow job is never picked up twice.
    public int $timeout = 80;

    public function __construct(public Receipt $receipt)
    {
    }

    /** ...while waiting for the OCR rate limit doesn't count, as long as it's within the hour. */
    public function retryUntil(): \DateTime
    {
        return now()->addHour();
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping($this->receipt->id), new RateLimited('ocr')];
    }

    public function handle(ReceiptReader $reader, ReceiptParser $parser, StoreIdentifier $stores): void
    {
        $receipt = $this->receipt;
        $receipt->update(['status' => ReceiptStatus::Processing, 'failure_reason' => null]);

        try {
            $ocr = $receipt->ocr_text !== null ? OcrResult::fromReceipt($receipt) : $this->readAndSave($reader);
        } catch (ReceiptProcessingException $e) {
            // Retrying won't help (no OCR set up, unreadable file), so fail straight away.
            $this->fail($e);

            return;
        }

        $parsed = $parser->parse($ocr);
        $store = $stores->identify($parsed, $ocr->text);

        DB::transaction(function () use ($receipt, $parsed, $store) {
            $receipt->update([
                'store_id' => $store?->id ?? $receipt->store_id,
                'purchased_at' => $parsed->purchasedAt ?? $receipt->purchased_at,
                'receipt_number' => $parsed->receiptNumber ?? $receipt->receipt_number,
                'subtotal_cents' => $parsed->subtotalCents,
                'discount_cents' => $parsed->discountCents,
                'total_cents' => $parsed->totalCents,
                'parsed_data' => [...($receipt->parsed_data ?? []), 'abn' => $parsed->abn, 'warnings' => $parsed->warnings],
                'status' => ReceiptStatus::NeedsReview,
                'processed_at' => now(),
            ]);

            $this->replaceItems($parsed);
        });
    }

    public function failed(?Throwable $e): void
    {
        $this->receipt->update([
            'status' => ReceiptStatus::Failed,
            'failure_reason' => $e instanceof ReceiptProcessingException
                ? $e->getMessage()
                : "We couldn't read this receipt. Try again, or upload a clearer photo.",
        ]);
    }

    /**
     * Run OCR and save the result straight away, so a later parsing error never costs a second OCR call.
     */
    private function readAndSave(ReceiptReader $reader): OcrResult
    {
        $ocr = $reader->read($this->receipt);

        $this->receipt->update([
            'ocr_provider' => $ocr->provider,
            'ocr_text' => $ocr->text,
            'parsed_data' => ['ocr_items' => $ocr->itemsToArray()],
        ]);

        return $ocr;
    }

    private function replaceItems(ParsedReceipt $parsed): void
    {
        // Deleting the items also deletes their price history (cascade).
        $this->receipt->items()->delete();

        foreach ($parsed->items as $index => $item) {
            /** @var ParsedItem $item */
            $this->receipt->items()->create([
                'line_number' => $index + 1,
                'original_name' => mb_substr($item->name, 0, 255),
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price_cents' => $item->unitPriceCents,
                'total_price_cents' => $item->totalPriceCents,
                'discount_cents' => $item->discountCents,
                'match_status' => MatchStatus::Pending,
                'raw_data' => ['line' => $item->raw],
            ]);
        }
    }
}
