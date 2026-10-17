<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => [
                'required', 'string', 'max:100',
                // The slug is unique, so two names that slug the same ("Soft Drinks", "Soft-drinks") clash.
                function ($attribute, $value, $fail) use ($category) {
                    $taken = Category::where('slug', Str::slug($value))->when($category, fn ($query) => $query->whereKeyNot($category->id))->exists();
                    $taken && $fail('A category with this name already exists.');
                },
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id'), Rule::notIn([$category?->id])],
        ];
    }
}
