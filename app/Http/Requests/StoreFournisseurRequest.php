<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

#[AllowDynamicProperties]
class StoreFournisseurRequest extends FormRequest
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
        $rules['ice'] = 'required';
        $rules['rc'] = 'required';
        $rules['nom'] = 'required';
        $rules['code'] = 'required';
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
            // ICE (Identifiant Commun de l'Entreprise)
            'ice.required' => 'Le champ ICE (Identifiant Commun de l\'Entreprise) est obligatoire.',

            // RC (Registre de Commerce)
            'rc.required' => 'Le champ RC (Registre de Commerce) est obligatoire.',

            // Nom
            'nom.required' => 'Le champ nom du fournisseur est obligatoire.',

            // Code
            'code.required' => 'Le champ code du fournisseur est obligatoire.',
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
            'ice' => 'ICE (Identifiant Commun de l\'Entreprise)',
            'rc' => 'RC (Registre de Commerce)',
            'nom' => 'nom du fournisseur',
            'code' => 'code du fournisseur',
        ];
    }
}
