<?php

namespace App\Receipts\Ocr;

interface OcrEngine
{
    /**
     * Read a receipt image or PDF.
     *
     * @throws \App\Receipts\ReceiptProcessingException when the file can't be read
     */
    public function read(string $contents, string $mimeType): OcrResult;
}
