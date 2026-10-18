<?php

namespace App\Receipts\Parsing;

use App\Receipts\Ocr\OcrResult;

interface ReceiptParser
{
    public function parse(OcrResult $ocr): ParsedReceipt;
}
