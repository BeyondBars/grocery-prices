<?php

namespace App\Http\Requests\Receipt;

use App\Http\Requests\Catalog\SaveProductRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One of: {"product_id": 12}, {"new_product": {...}} or {"ignore": true|false}.
 */
class MatchReceiptItemRequest extends FormRequest
{
    public function rules(): array
    {
        $productRules = collect(SaveProductRequest::productRules())
            ->mapWithKeys(fn ($rules, $field) => ["new_product.{$field}" => ['exclude_without:new_product', ...$rules]])
            ->all();

        return [
            'ignore' => ['sometimes', 'boolean'],
            'product_id' => ['required_without_all:new_product,ignore', 'nullable', 'integer', Rule::exists('products', 'id')->whereNull('merged_into_id')],
            'new_product' => ['required_without_all:product_id,ignore', 'array'],
            ...$productRules,
        ];
    }
}
