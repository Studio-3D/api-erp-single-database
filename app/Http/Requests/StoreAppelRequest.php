<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

#[AllowDynamicProperties]
class StoreAppelRequest extends FormRequest
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
    public function rules(Request $request): array
    {
        $rules = [];
        $rules['telephone'] = 'required|min:10|max:14';
        $rules['source'] = 'required';
        $rules['type_appel'] = 'required';
        $rules['interet'] = 'required';
        // $rules['commentaire'] = 'required';
        $rules['projet_id'] = 'required';

        if ($request->telephone_num2 != "null" && $request->telephone_num2 != null) {
            $rules['telephone_num2'] = 'min:10|max:14';
        }
        if ($request->source_txt === 'PARTENAIRE' || $request->source_txt === 'Partenaire') {
            $rules['partenaire_id'] = 'required';
        }
        if ($request->interet == 3) {
            // perdu
            $rules['freins'] = 'required';
        } elseif ($request->interet == 1) {
            // interessé
            $rules['type_biens'] = 'required';
            $rules['orientation'] = 'required';
        }

        return $rules;
    }

    /**
     * Get the validation error messages in French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Telephone
            'telephone.required' => 'Le numéro de téléphone est obligatoire.',
            'telephone.min' => 'Le numéro de téléphone doit contenir au moins :min caractères.',
            'telephone.max' => 'Le numéro de téléphone ne peut pas dépasser :max caractères.',

            // Telephone num2
            'telephone_num2.min' => 'Le deuxième numéro de téléphone doit contenir au moins :min caractères.',
            'telephone_num2.max' => 'Le deuxième numéro de téléphone ne peut pas dépasser :max caractères.',

            // Source
            'source.required' => 'La source de l\'appel est obligatoire.',

            // Type appel
            'type_appel.required' => 'Le type d\'appel est obligatoire.',

            // Interet
            'interet.required' => 'Le niveau d\'intérêt est obligatoire.',

            // Projet
            'projet_id.required' => 'Le projet est obligatoire.',

            // Partenaire
            'partenaire_id.required' => 'Le partenaire est obligatoire lorsque la source est "Partenaire".',

            // Freins (perdu)
            'freins.required' => 'Le champ freins est obligatoire lorsque le client n\'est pas intéressé.',

            // Type biens (interessé)
            'type_biens.required' => 'Le type de biens est obligatoire lorsque le client est intéressé.',

            // Orientation (interessé)
            'orientation.required' => 'L\'orientation est obligatoire lorsque le client est intéressé.',
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
            'telephone' => 'numéro de téléphone',
            'telephone_num2' => 'deuxième numéro de téléphone',
            'source' => 'source de l\'appel',
            'type_appel' => 'type d\'appel',
            'interet' => 'niveau d\'intérêt',
            'projet_id' => 'projet',
            'partenaire_id' => 'partenaire',
            'freins' => 'freins',
            'type_biens' => 'type de biens',
            'orientation' => 'orientation',
        ];
    }
}
