<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'abn' => $this->abn ? preg_replace('/\D/', '', $this->abn) : null,
            'aliases' => normalise_aliases($this->aliases),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('stores')->ignore($this->route('store'))],
            'abn' => ['nullable', 'digits:11'],
            'aliases' => ['array', 'max:20'],
            'aliases.*' => ['string', 'max:100'],
        ];
    }
}
