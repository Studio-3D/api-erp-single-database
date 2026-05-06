<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'string',
            'prenom' => 'string',
            'role' => 'integer|nullable',
            'phone' => 'string|min:10|max:14|nullable',
            'photo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048|nullable',
            'date_embauche' => 'date|nullable',
            'cnss' => 'integer|nullable',
            'is_actif' => 'integer|nullable',
            'solde_conge' => 'integer|nullable',
            'password' => 'min:6|nullable',
            // rien ne va changer avec cette validation
            /* 'cin' => [
                Rule::unique('users')->ignore($this->user),
            ], */
        ];
    }

    /**
     * Get the validation error messages in French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Name
            'name.string' => 'Le nom doit être une chaîne de caractères.',

            // Prénom
            'prenom.string' => 'Le prénom doit être une chaîne de caractères.',

            // Rôle
            'role.integer' => 'Le rôle doit être un nombre entier.',

            // Téléphone
            'phone.string' => 'Le numéro de téléphone doit être une chaîne de caractères.',
            'phone.min' => 'Le numéro de téléphone doit contenir au moins :min caractères.',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser :max caractères.',

            // Photo
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'La photo doit être au format : png, jpg ou jpeg.',
            'photo.max' => 'La photo ne doit pas dépasser :max kilo-octets (2 Mo).',

            // Date d'embauche
            'date_embauche.date' => 'La date d\'embauche doit être une date valide.',

            // CNSS
            'cnss.integer' => 'Le numéro CNSS doit être un nombre entier.',

            // Statut actif
            'is_actif.integer' => 'Le statut actif doit être un nombre entier.',

            // Solde congé
            'solde_conge.integer' => 'Le solde de congé doit être un nombre entier.',

            // Mot de passe
            'password.min' => 'Le mot de passe doit contenir au moins :min caractères.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'prenom' => 'prénom',
            'role' => 'rôle',
            'phone' => 'numéro de téléphone',
            'photo' => 'photo',
            'date_embauche' => 'date d\'embauche',
            'cnss' => 'CNSS',
            'is_actif' => 'statut actif',
            'solde_conge' => 'solde de congé',
            'password' => 'mot de passe',
        ];
    }
}
