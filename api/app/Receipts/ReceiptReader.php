<?php

namespace App\Receipts;

use App\Models\Receipt;
use App\Receipts\Ocr\OcrEngine;
use App\Receipts\Ocr\OcrResult;
use App\Receipts\Ocr\PdfTextExtractor;
use Illuminate\Support\Facades\Storage;

/**
 * Gets the text out of an uploaded receipt the cheapest way that works: the PDF's own text when
 * it has some, otherwise the OCR engine.
 */
class ReceiptReader
{
    public function __construct(private PdfTextExtractor $pdfText, private OcrEngine $ocr)
    {
    }

    public function read(Receipt $receipt): OcrResult
    {
        $contents = Storage::disk(config('receipts.disk'))->get($receipt->file_path);

        if ($contents === null) {
            throw new ReceiptProcessingException('The uploaded file is missing. Please upload the receipt again.');
        }

        if ($receipt->file_mime === 'application/pdf' && ($text = $this->pdfText->extract($contents)) !== null) {
            return (new OcrResult('pdftotext', $text))->masked();
        }

        return $this->ocr->read($contents, $receipt->file_mime)->masked();
    }
}
