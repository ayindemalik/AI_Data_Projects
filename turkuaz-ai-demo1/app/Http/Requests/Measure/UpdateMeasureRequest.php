<?php

namespace App\Http\Requests\Measure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $measureId = $this->route('measure')->id;

        return [
            'name.tr' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:20'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('measures', 'slug')->ignore($measureId)],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
