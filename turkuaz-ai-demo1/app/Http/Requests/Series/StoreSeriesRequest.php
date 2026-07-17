<?php

namespace App\Http\Requests\Series;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeriesRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:255', 'unique:series,slug', 'alpha_dash'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
