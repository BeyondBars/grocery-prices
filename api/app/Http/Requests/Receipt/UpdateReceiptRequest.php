<?php

namespace App\Http\Requests\Receipt;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corrections to what was read from the receipt header.
 */
class UpdateReceiptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'store_id' => ['sometimes', 'nullable', 'integer', Rule::exists('stores', 'id')],
            'store_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'purchased_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:tomorrow'],
            'receipt_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'subtotal_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'discount_cents' => ['sometimes', 'integer', 'min:0'],
            'total_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
