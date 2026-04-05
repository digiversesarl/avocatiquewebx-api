<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'parent_id'  => ['sometimes', 'nullable', 'integer', 'exists:menu_items,id'],
            'label_fr'   => ['required', 'string', 'max:255'],
            'label_ar'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'label_en'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'route'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_visible' => ['sometimes', 'boolean'],
            'module'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'roles'      => ['sometimes', 'array'],
            'roles.*'    => ['string', 'exists:roles,name'],
        ];
    }
}
