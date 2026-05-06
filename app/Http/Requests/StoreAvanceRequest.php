<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class StoreAvanceRequest extends FormRequest
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
        $rules['montant'] = 'required';
        $rules['mode_paiement'] = 'required';

        // mode_paiement chèque/chèque banque/chèque certifié
        if ($request->mode_paiement == 2 || $request->mode_paiement == 3 || $request->mode_paiement == 4) {
            $rules['banque_id'] = 'required';
            $rules['numero_paiement'] = 'required';
            $rules['echeance'] = 'required';
        }
        // virement versement
        elseif ($request->mode_paiement == 5 || $request->mode_paiement == 6) {
            $rules['banque_id'] = 'required';
            $rules['numero_paiement'] = 'required';
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
            // Montant
            'montant.required' => 'Le champ montant est obligatoire.',

            // Mode paiement
            'mode_paiement.required' => 'Le mode de paiement est obligatoire.',

            // Banque
            'banque_id.required' => 'La banque est obligatoire pour ce mode de paiement.',

            // Numéro paiement
            'numero_paiement.required' => 'Le numéro de paiement est obligatoire pour ce mode de paiement.',

            // Échéance
            'echeance.required' => 'La date d\'échéance est obligatoire pour ce mode de paiement.',
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
            'montant' => 'montant',
            'mode_paiement' => 'mode de paiement',
            'banque_id' => 'banque',
            'numero_paiement' => 'numéro de paiement',
            'echeance' => 'date d\'échéance',
        ];
    }
}
