<?php

namespace App\Receipts;

use RuntimeException;

/**
 * A processing failure whose message is safe to show the user and that retrying won't fix.
 */
class ReceiptProcessingException extends RuntimeException
{
}
