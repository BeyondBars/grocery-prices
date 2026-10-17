<?php

namespace App\Http\Requests\Receipt;

use App\Support\Enums\ItemUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corrections to an OCR'd receipt line (a misread name, quantity or price).
 */
class UpdateReceiptItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'original_name' => ['sometimes', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'gt:0', 'max:9999'],
            'unit' => ['sometimes', Rule::enum(ItemUnit::class)],
            'unit_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'total_price_cents' => ['sometimes', 'integer'],
            'discount_cents' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
