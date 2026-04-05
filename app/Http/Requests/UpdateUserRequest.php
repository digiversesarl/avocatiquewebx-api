<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'nullable', Password::min(8)->letters()->numbers()],
            'status'   => ['sometimes', 'in:active,inactive'],
            'roles'    => ['sometimes', 'array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ];
    }
}
