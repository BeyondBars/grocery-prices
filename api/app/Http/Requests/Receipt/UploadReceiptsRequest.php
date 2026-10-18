<?php

namespace App\Http\Requests\Receipt;

use Illuminate\Foundation\Http\FormRequest;

class UploadReceiptsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:'.config('receipts.max_files')],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('receipts.max_file_kb')],
        ];
    }

    public function messages(): array
    {
        return [
            'files.*.mimes' => 'Receipts must be photos (JPG or PNG) or PDFs.',
            'files.*.max' => 'Each receipt must be smaller than 10 MB.',
            'files.max' => 'Upload up to :max receipts at a time.',
        ];
    }
}
