<?php

namespace App\Receipts\Ocr;

use Illuminate\Support\Facades\Process;

/**
 * Reads the text layer of a PDF with pdftotext (poppler-utils). Emailed and downloaded e-receipts
 * have one, so they need no OCR at all. Scanned PDFs have none and return null.
 */
class PdfTextExtractor
{
    public function extract(string $contents): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'receipt');
        file_put_contents($path, $contents);

        try {
            $result = Process::timeout(30)->run([config('receipts.pdftotext_binary'), '-layout', $path, '-']);
        } finally {
            @unlink($path);
        }

        $text = trim($result->output());

        // A handful of characters is a stray page number or logo text, not a receipt.
        return $result->successful() && mb_strlen($text) >= 20 ? $text : null;
    }
}
