<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompositionBienRequest extends FormRequest
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
            'bien_id' => 'required|integer',
            'nbre_chambres' => 'integer',
            'nbre_salons' => 'integer',
            'nbre_sdb' => 'integer',
            'nbre_cuisines' => 'integer',
            'nbre_halls' => 'integer',
            'nbre_terasses' => 'integer',
            'nbre_balcons' => 'integer',
            'nbre_buanderies' => 'integer',
            'nbre_placards' => 'integer',
            'nbre_receptions' => 'integer',
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
            // Bien ID
            'bien_id.required' => 'Le champ bien est obligatoire.',
            'bien_id.integer' => 'L\'identifiant du bien doit être un nombre entier.',

            // Nombre de chambres
            'nbre_chambres.integer' => 'Le nombre de chambres doit être un nombre entier.',

            // Nombre de salons
            'nbre_salons.integer' => 'Le nombre de salons doit être un nombre entier.',

            // Nombre de salles de bain
            'nbre_sdb.integer' => 'Le nombre de salles de bain doit être un nombre entier.',

            // Nombre de cuisines
            'nbre_cuisines.integer' => 'Le nombre de cuisines doit être un nombre entier.',

            // Nombre de halls
            'nbre_halls.integer' => 'Le nombre de halls doit être un nombre entier.',

            // Nombre de terrasses
            'nbre_terasses.integer' => 'Le nombre de terrasses doit être un nombre entier.',

            // Nombre de balcons
            'nbre_balcons.integer' => 'Le nombre de balcons doit être un nombre entier.',

            // Nombre de buanderies
            'nbre_buanderies.integer' => 'Le nombre de buanderies doit être un nombre entier.',

            // Nombre de placards
            'nbre_placards.integer' => 'Le nombre de placards doit être un nombre entier.',

            // Nombre de réceptions
            'nbre_receptions.integer' => 'Le nombre de réceptions doit être un nombre entier.',
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
            'bien_id' => 'bien',
            'nbre_chambres' => 'nombre de chambres',
            'nbre_salons' => 'nombre de salons',
            'nbre_sdb' => 'nombre de salles de bain',
            'nbre_cuisines' => 'nombre de cuisines',
            'nbre_halls' => 'nombre de halls',
            'nbre_terasses' => 'nombre de terrasses',
            'nbre_balcons' => 'nombre de balcons',
            'nbre_buanderies' => 'nombre de buanderies',
            'nbre_placards' => 'nombre de placards',
            'nbre_receptions' => 'nombre de réceptions',
        ];
    }
}
