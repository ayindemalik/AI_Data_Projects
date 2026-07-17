<?php

namespace App\Http\Requests\Measure;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeasureRequest extends FormRequest
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
            'unit' => ['required', 'string', 'max:20'],
            'slug' => ['required', 'string', 'max:255', 'unique:measures,slug', 'alpha_dash'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
