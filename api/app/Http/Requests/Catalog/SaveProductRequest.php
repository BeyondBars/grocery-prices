<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use App\Support\Size;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function rules(): array
    {
        return $this->rulesFor($this->route('product'));
    }

    private function rulesFor(?Product $ignore): array
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
            'barcode' => ['nullable', 'digits_between:8,14', Rule::unique('products')->ignore($ignore)],
        ];
    }

    /**
     * Validated attributes ready for the model, with the size split into base amount and unit.
     */
    public function productAttributes(): array
    {
        return [
            ...$this->safe()->except(['size']),
            'pack_count' => $this->validated('pack_count') ?? 1,
            ...Product::sizeAttributes($this->validated('size')),
        ];
    }

    /**
     * The product rules, for reuse where a product is created inside another request.
     */
    public static function productRules(?Product $ignore = null): array
    {
        return (new self)->setRouteResolver(fn () => null)->rulesFor($ignore);
    }
}
