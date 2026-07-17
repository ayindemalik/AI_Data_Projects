<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Actual permission check happens in the controller via the UserPolicy.
        return true;
    }

    public function rules(): array
    {
        // The user being edited, from the {user} route parameter — used to
        // exclude their own current email from the "unique" check.
        $userId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            // Password is optional on update — leaving it blank keeps the current password.
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
        ];
    }
}
