<?php

namespace App\Receipts\Ocr;

use App\Receipts\ReceiptProcessingException;

/**
 * Used when no OCR service is configured (RECEIPTS_OCR_ENGINE=none). PDFs with text still work.
 */
class NullOcrEngine implements OcrEngine
{
    public function read(string $contents, string $mimeType): OcrResult
    {
        throw new ReceiptProcessingException('Reading photos of receipts is not set up yet. PDF receipts still work.');
    }
}
