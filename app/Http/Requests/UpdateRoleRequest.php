<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'display_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'   => ['sometimes', 'nullable', 'string'],
            'level'         => ['sometimes', 'integer', 'min:0', 'max:999'],
            'color'         => ['sometimes', 'nullable', 'string', 'max:7'],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
