<?php

namespace App\Http\Requests\Series;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $seriesId = $this->route('series')->id;

        return [
            'name.tr' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'description.tr' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('series', 'slug')->ignore($seriesId)],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
