<?php

namespace App\Support\Enums;

enum ReceiptStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Completed = 'completed';
    case Failed = 'failed';
}
