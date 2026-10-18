<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Receipt storage
    |--------------------------------------------------------------------------
    |
    | Uploaded receipts hold personal data, so they go on a private disk and are only ever
    | served through the API after a policy check. "local" is storage/app (not public);
    | use "s3" in production.
    |
    */

    'disk' => env('RECEIPTS_DISK', 'local'),

    // Per upload request.
    'max_files' => 10,
    'max_file_kb' => 10240,

    // Photos are re-encoded as JPEG no larger than this on their longest side. Re-encoding also
    // drops EXIF data such as the GPS location of where the photo was taken.
    'image_max_px' => 2000,
    'image_quality' => 85,

    /*
    |--------------------------------------------------------------------------
    | OCR
    |--------------------------------------------------------------------------
    |
    | PDFs that already contain text are read with pdftotext (free and exact). Everything else
    | goes to the OCR engine below: "textract" (AWS Textract AnalyzeExpense) or "none", which
    | fails photo receipts with a clear message (handy locally without AWS credentials).
    |
    */

    'ocr_engine' => env('RECEIPTS_OCR_ENGINE', 'none'),

    'pdftotext_binary' => env('PDFTOTEXT_BINARY', 'pdftotext'),

    'textract' => [
        'region' => env('AWS_DEFAULT_REGION', 'ap-southeast-2'),
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    // OCR calls per minute across all workers, to stay inside the provider's limits.
    'ocr_per_minute' => env('RECEIPTS_OCR_PER_MINUTE', 60),

];
