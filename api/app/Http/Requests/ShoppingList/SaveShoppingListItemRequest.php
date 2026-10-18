<?php

namespace App\Http\Requests\ShoppingList;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A product, or a plain note ("Bread") to link to a product later.
 */
class SaveShoppingListItemRequest extends FormRequest
{
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'product_id' => [$creating ? 'required_without:free_text' : 'sometimes', 'nullable', 'integer', Rule::exists('products', 'id')->whereNull('merged_into_id')],
            'free_text' => [$creating ? 'required_without:product_id' : 'sometimes', 'nullable', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'gt:0', 'max:999'],
            'is_checked' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
