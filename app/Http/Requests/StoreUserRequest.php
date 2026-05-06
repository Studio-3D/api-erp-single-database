<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            // 'name' => 'required|string',
            // 'prenom' => 'required|string',
            'email' => [
                'required',
                'email',
                'max:254',
                // Check uniqueness across all users
                Rule::unique('users', 'email')->where(function ($query) {
                    return $query->whereNotNull('email');
                }),
            ],
            'password' => 'required|min:6|same:password_confirmation',
            'password_confirmation' => 'required|min:6',
            'role' => 'required|integer',
            'phone' => 'string|min:10|max:14|nullable',
            'photo' => 'image|mimes:png,jpg,jpeg|max:2048|nullable',
            'cin' => 'string|unique:users,cin,NULL,id,deleted_at,NULL|nullable',
            'date_embauche' => 'date|nullable',
            'cnss' => 'integer|nullable',
            'is_actif' => 'integer|nullable',
            // 'nb_appel_recu' => 'integer',
            // 'nb_appel_traite' => 'integer',
            'solde_conge' => 'integer|nullable',
            'societe_id' => 'required|integer',
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
            // Email
            'email.required' => 'L\'adresse email est requise.',
            'email.email' => 'Veuillez saisir une adresse email valide.',
            'email.max' => 'L\'adresse email ne peut pas dépasser :max caractères.',
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre utilisateur. Veuillez choisir une autre adresse email.',

            // Password
            'password.required' => 'Le champ mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins :min caractères.',
            'password.same' => 'La confirmation du mot de passe ne correspond pas.',

            // Password confirmation
            'password_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
            'password_confirmation.min' => 'La confirmation du mot de passe doit contenir au moins :min caractères.',

            // Role
            'role.required' => 'Le champ rôle est obligatoire.',
            'role.integer' => 'Le rôle doit être un nombre entier.',

            // Phone
            'phone.string' => 'Le numéro de téléphone doit être une chaîne de caractères.',
            'phone.min' => 'Le numéro de téléphone doit contenir au moins :min caractères.',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser :max caractères.',

            // Photo
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'La photo doit être au format : png, jpg ou jpeg.',
            'photo.max' => 'La photo ne doit pas dépasser :max kilo-octets (2 Mo).',

            // CIN
            'cin.string' => 'Le CIN doit être une chaîne de caractères.',
            'cin.unique' => 'Le CIN que vous avez saisi appartient à un autre utilisateur.',

            // Date embauche
            'date_embauche.date' => 'La date d\'embauche doit être une date valide.',

            // CNSS
            'cnss.integer' => 'Le numéro CNSS doit être un nombre entier.',

            // Solde congé
            'solde_conge.integer' => 'Le solde de congé doit être un nombre entier.',

            // Société ID
            'societe_id.required' => 'La société est requise.',
            'societe_id.integer' => 'L\'identifiant de la société doit être un nombre entier.',
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
            'email' => 'adresse email',
            'password' => 'mot de passe',
            'password_confirmation' => 'confirmation du mot de passe',
            'role' => 'rôle',
            'phone' => 'numéro de téléphone',
            'photo' => 'photo',
            'cin' => 'CIN',
            'date_embauche' => 'date d\'embauche',
            'cnss' => 'CNSS',
            'is_actif' => 'statut actif',
            'solde_conge' => 'solde de congé',
            'societe_id' => 'société',
        ];
    }
}
