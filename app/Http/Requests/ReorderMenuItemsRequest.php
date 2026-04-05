<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderMenuItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items'         => ['required', 'array', 'min:1'],
            'items.*.id'    => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.ordre' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required'         => 'La liste des éléments est obligatoire.',
            'items.*.id.exists'      => 'Un identifiant d\'élément de menu est invalide.',
            'items.*.ordre.integer'  => 'L\'ordre doit être un entier.',
        ];
    }
}
