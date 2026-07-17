<?php

namespace App\Http\Requests\Collection;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionRequest extends FormRequest
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
            'description.tr' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'slug' => ['required', 'string', 'max:255', 'unique:collections,slug', 'alpha_dash'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
