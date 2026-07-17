<?php

namespace App\Http\Requests\DocumentCategory;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name.tr' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:document_categories,slug', 'alpha_dash'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
