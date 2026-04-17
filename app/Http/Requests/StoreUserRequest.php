<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'login'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'password'              => ['required', Password::min(8)->letters()->numbers()],
            'status'                => ['sometimes', 'in:active,inactive'],
            'full_name_fr'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'full_name_ar'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'abbreviation_fr'       => ['sometimes', 'nullable', 'string', 'max:10'],
            'abbreviation_ar'       => ['sometimes', 'nullable', 'string', 'max:10'],
            'telephone'             => ['sometimes', 'nullable', 'string', 'max:20'],
            'cin'                   => ['sometimes', 'nullable', 'string', 'max:20'],
            'rib'                   => ['sometimes', 'nullable', 'string', 'max:34'],
            'date_entree'           => ['sometimes', 'nullable', 'date'],
            'langue'                => ['sometimes', 'in:fr,ar,en'],
            'fonction'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'grade_avocat'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'departement'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_fr'            => ['sometimes', 'nullable', 'string'],
            'address_ar'            => ['sometimes', 'nullable', 'string'],
            'avocat_proprietaire'   => ['sometimes', 'boolean'],
            'active'                => ['sometimes', 'boolean'],
            'is_admin'              => ['sometimes', 'boolean'],
            'classement'            => ['sometimes', 'nullable', 'string', 'max:10'],
            'tarif_journalier'      => ['sometimes', 'nullable', 'numeric'],
            'observation'           => ['sometimes', 'nullable', 'string'],
            'couleur_fond'          => ['sometimes', 'nullable', 'string', 'max:7'],
            'couleur_texte'         => ['sometimes', 'nullable', 'string', 'max:7'],
            'valeur_par_defaut'     => ['sometimes', 'boolean'],
            'tfa_enabled'           => ['sometimes', 'boolean'],
            'photo'                 => ['sometimes', 'nullable', 'string'], // Accept base64 strings or empty
            'roles'                 => ['sometimes', 'array'],
            'roles.*'               => ['string', 'exists:roles,name'],
            'groupes'               => ['sometimes', 'array'],
            'groupes.*'             => ['string', 'exists:groupes,label_fr'],
            'departements'          => ['sometimes', 'array'],
            'departements.*'        => ['string', 'exists:departements,label_fr'],
        ];
    }
}
