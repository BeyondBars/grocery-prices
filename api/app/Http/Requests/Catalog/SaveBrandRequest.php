<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBrandRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['aliases' => normalise_aliases($this->aliases)]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('brands')->ignore($this->route('brand'))],
            'aliases' => ['array', 'max:20'],
            'aliases.*' => ['string', 'max:100'],
        ];
    }
}
