<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'exists:subcategories,id'],
            'product_type_id' => ['nullable', 'exists:product_types,id'],
            'series_id' => ['nullable', 'exists:series,id'],

            'sku' => ['nullable', 'string', 'max:190', 'unique:products,sku'],
            'sku_new' => ['nullable', 'string', 'max:190'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug', 'alpha_dash'],
            'name.tr' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'description.tr' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'dimensions' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],

            'colors' => ['nullable', 'array'],
            'colors.*' => ['exists:colors,id'],

            'measures' => ['nullable', 'array'],
            'measures.*' => ['nullable', 'numeric'],

            'variant_sku' => ['nullable', 'array'],
            'variant_sku.*' => ['nullable', 'string', 'max:190'],
            'variant_note_tr' => ['nullable', 'array'],
            'variant_note_en' => ['nullable', 'array'],

            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
