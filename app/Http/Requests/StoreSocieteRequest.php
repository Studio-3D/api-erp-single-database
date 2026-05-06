<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocieteRequest extends FormRequest
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
            'raison_sociale' => 'required|unique:societes,raison_sociale,NULL,id,deleted_at,NULL|nullable',
            'nom_contact' => 'required|min:3',
            'prenom_contact' => 'required|min:3',
            'tel' => 'min:10|max:14|nullable',
            'email' => 'required|email|nullable',
            'logo' => 'image|mimes:png,jpg,jpeg|max:2048|nullable',
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
            // Raison sociale
            'raison_sociale.required' => 'Le champ raison sociale est obligatoire.',
            'raison_sociale.unique' => 'Cette raison sociale existe déjà.',

            // Nom contact
            'nom_contact.required' => 'Le champ nom du contact est obligatoire.',
            'nom_contact.min' => 'Le nom du contact doit contenir au moins :min caractères.',

            // Prénom contact
            'prenom_contact.required' => 'Le champ prénom du contact est obligatoire.',
            'prenom_contact.min' => 'Le prénom du contact doit contenir au moins :min caractères.',

            // Téléphone
            'tel.min' => 'Le numéro de téléphone doit contenir au moins :min caractères.',
            'tel.max' => 'Le numéro de téléphone ne peut pas dépasser :max caractères.',

            // Email
            'email.required' => 'Le champ email est obligatoire.',
            'email.email' => 'Veuillez saisir une adresse email valide.',

            // Logo
            'logo.image' => 'Le fichier doit être une image.',
            'logo.mimes' => 'Le logo doit être au format : png, jpg ou jpeg.',
            'logo.max' => 'Le logo ne doit pas dépasser :max kilo-octets (2 Mo).',
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
            'raison_sociale' => 'raison sociale',
            'nom_contact' => 'nom du contact',
            'prenom_contact' => 'prénom du contact',
            'tel' => 'numéro de téléphone',
            'email' => 'adresse email',
            'logo' => 'logo',
        ];
    }
}
