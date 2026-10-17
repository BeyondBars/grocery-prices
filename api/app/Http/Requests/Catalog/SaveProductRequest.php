<?php

namespace App\Http\Requests\Catalog;

use App\Support\Size;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'variant' => ['nullable', 'string', 'max:100'],
            // Written the way it appears on the pack: "2L", "700g", "1.25 L", "12 each".
            'size' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                Size::parse($value) || $fail('Enter a size like 2L, 500ml, 700g or 1kg.');
            }],
            'pack_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'barcode' => ['nullable', 'digits_between:8,14', Rule::unique('products')->ignore($this->route('product'))],
        ];
    }

    /**
     * Validated attributes ready for the model, with the size split into base amount and unit.
     */
    public function productAttributes(): array
    {
        $size = Size::parse($this->validated('size'));

        return [
            ...$this->safe()->except(['size']),
            'pack_count' => $this->validated('pack_count') ?? 1,
            'size_amount' => $size?->amount,
            'size_unit' => $size?->unit,
        ];
    }
}
