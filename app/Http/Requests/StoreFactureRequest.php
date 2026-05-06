<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

#[AllowDynamicProperties]
class StoreFactureRequest extends FormRequest
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
        $rules['fournisseur_id'] = 'required';
        $rules['decompte_id'] = 'required';
        $rules['date_facture'] = 'required';
        $rules['num_facture'] = 'required';
        $rules['piece_jointe'] = 'required';
        $rules['ht'] = 'required';
        $rules['taux_tva'] = 'required';
        $rules['tva'] = 'required';
        $rules['retenue_garantie'] = 'required';
        $rules['ttc'] = 'required';
        $rules['montant'] = 'required';
        $rules['date_paiement'] = 'required';
        $rules['mode_paiement'] = 'required';

        // mode_paiement chèque/chèque banque/chèque certifié
        if ($request->mode_paiement == 2 || $request->mode_paiement == 3 || $request->mode_paiement == 4) {
            $rules['banque_id'] = 'required';
            $rules['numero_paiement'] = 'required';
            $rules['date_echeance'] = 'required';
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
            // Fournisseur
            'fournisseur_id.required' => 'Le champ fournisseur est obligatoire.',

            // Décompte
            'decompte_id.required' => 'Le champ décompte est obligatoire.',

            // Date facture
            'date_facture.required' => 'Le champ date de facture est obligatoire.',

            // Numéro facture
            'num_facture.required' => 'Le champ numéro de facture est obligatoire.',

            // Pièce jointe
            'piece_jointe.required' => 'Le champ pièce jointe est obligatoire.',

            // HT (Hors Taxe)
            'ht.required' => 'Le champ montant hors taxe (HT) est obligatoire.',

            // Taux TVA
            'taux_tva.required' => 'Le champ taux de TVA est obligatoire.',

            // TVA
            'tva.required' => 'Le champ montant de TVA est obligatoire.',

            // Retenue garantie
            'retenue_garantie.required' => 'Le champ retenue de garantie est obligatoire.',

            // TTC (Toutes Taxes Comprises)
            'ttc.required' => 'Le champ montant toutes taxes comprises (TTC) est obligatoire.',

            // Montant
            'montant.required' => 'Le champ montant est obligatoire.',

            // Date paiement
            'date_paiement.required' => 'Le champ date de paiement est obligatoire.',

            // Mode paiement
            'mode_paiement.required' => 'Le champ mode de paiement est obligatoire.',

            // Banque (pour chèque et virement)
            'banque_id.required' => 'La banque est obligatoire pour ce mode de paiement.',

            // Numéro paiement
            'numero_paiement.required' => 'Le numéro de paiement est obligatoire pour ce mode de paiement.',

            // Date échéance (pour chèque)
            'date_echeance.required' => 'La date d\'échéance est obligatoire pour ce mode de paiement.',
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
            'fournisseur_id' => 'fournisseur',
            'decompte_id' => 'décompte',
            'date_facture' => 'date de facture',
            'num_facture' => 'numéro de facture',
            'piece_jointe' => 'pièce jointe',
            'ht' => 'montant hors taxe (HT)',
            'taux_tva' => 'taux de TVA',
            'tva' => 'montant de TVA',
            'retenue_garantie' => 'retenue de garantie',
            'ttc' => 'montant toutes taxes comprises (TTC)',
            'montant' => 'montant',
            'date_paiement' => 'date de paiement',
            'mode_paiement' => 'mode de paiement',
            'banque_id' => 'banque',
            'numero_paiement' => 'numéro de paiement',
            'date_echeance' => 'date d\'échéance',
        ];
    }
}
