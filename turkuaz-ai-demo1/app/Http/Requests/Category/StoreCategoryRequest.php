<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission check happens in the controller via the Policy.
    }

    public function rules(): array
    {
        return [
            'name.tr' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug', 'alpha_dash'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
