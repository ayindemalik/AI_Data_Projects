<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_category_id' => ['nullable', 'exists:document_categories,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'type' => ['required', Rule::in(Document::TYPES)],
            'title.tr' => ['required', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],

            // Either a URL typed in, or a file uploaded, per language.
            'file_url_tr' => ['nullable', 'url', 'max:500', 'required_without:file_upload_tr'],
            'file_url_en' => ['nullable', 'url', 'max:500'],
            'file_upload_tr' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
            'file_upload_en' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],

            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
