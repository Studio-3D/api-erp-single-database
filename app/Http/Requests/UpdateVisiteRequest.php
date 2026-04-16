<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class UpdateVisiteRequest extends FormRequest
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
        $rules['source_id'] = 'required';
       // $rules['nom'] = 'required|string';
        $rules['prenom'] = 'required|string';
        $rules['interet'] = 'required';
        $rules['telephone_num2'] = 'nullable';

        // Condition qui accepte toutes les variantes de "Partenaire" (majuscules, minuscules, mixte)
        if (strcasecmp($request->source_txt, 'PARTENAIRE') == 0) {
            $rules['partenaire_id'] = 'required';
        }

        // interessé
        if ($request->interet == 1) {
            $rules['bien_id'] = 'required';
            $rules['statut'] = 'required';
            $rules['cin'] = 'required';
        }
        // perdu
        elseif ($request->interet == 3) {
            $rules['frein'] = 'required';
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
            // Téléphone
            'telephone.required' => 'Le numéro de téléphone est obligatoire.',
            'telephone.min' => 'Le numéro de téléphone doit contenir au moins :min caractères.',
            'telephone.max' => 'Le numéro de téléphone ne peut pas dépasser :max caractères.',

            // Source ID
            'source_id.required' => 'Le champ source est obligatoire.',

            // Nom
            'nom.required' => 'Le champ nom est obligatoire.',
            'nom.string' => 'Le nom doit être une chaîne de caractères.',

            // Prénom
            'prenom.required' => 'Le champ prénom est obligatoire.',
            'prenom.string' => 'Le prénom doit être une chaîne de caractères.',

            // Intérêt
            'interet.required' => 'Le champ niveau d\'intérêt est obligatoire.',

            // Partenaire (conditionnel)
            'partenaire_id.required' => 'Le partenaire est obligatoire lorsque la source est "Partenaire".',

            // Bien ID (intéressé)
            'bien_id.required' => 'Le champ bien est obligatoire lorsque le client est intéressé.',

            // Statut (intéressé)
            'statut.required' => 'Le champ statut est obligatoire lorsque le client est intéressé.',

            // CIN (intéressé)
            'cin.required' => 'Le champ CIN est obligatoire lorsque le client est intéressé.',

            // Frein (perdu)
            'frein.required' => 'Le champ frein est obligatoire lorsque le client n\'est pas intéressé.',
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
            'source_id' => 'source',
            'nom' => 'nom',
            'prenom' => 'prénom',
            'interet' => 'niveau d\'intérêt',
            'telephone_num2' => 'deuxième numéro de téléphone',
            'partenaire_id' => 'partenaire',
            'bien_id' => 'bien',
            'statut' => 'statut',
            'cin' => 'CIN',
            'frein' => 'frein',
        ];
    }
}
