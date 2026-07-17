<?php

namespace App\Http\Requests\Collection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $collectionId = $this->route('collection')->id;

        return [
            'name.tr' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'description.tr' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('collections', 'slug')->ignore($collectionId)],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
