<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class StoreDesistementRequest extends FormRequest
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
        $rules['type'] = 'required';
        $rules['reservation_id'] = 'required';

        // dd
        if ($request->type == 1) {
            if ($request->sum_avances_valides > 0) {
                // mode remboursement obligatoire
                $rules['type_remb'] = 'required';
                if ($request->type_remb != null) {
                    if ($request->type_remb == 'transfert') {
                        $rules['dossier_id'] = 'required';
                    }
                    /*if($request->type_remb=='direct'){
                        $rules['date_remboursement']='required';
                        $rules['mode_remboursement']='required';
                        $rules['num_paiement']='required';
                        $rules['pour_le_compte']='required';
                        if($request->pour_le_compte=='autre'){
                            //  $rules['fichier_autorisation']='required';
                        }
                        if($request->mode_remboursement=='cheque'){
                            // $rules['cheque_recu']='required';
                         }

                    }
                    elseif($request->type_remb=='transfert'){
                        $rules['dossier_id']='required';
                    }
                    elseif($request->type_remb=='transfert_remb'){
                        $rules['montant_transferer']='required';
                        $rules['type_remb_transfere']='required';
                        if($request->type_remb_transfere=='immediat'){

                            $rules['date_remboursement']='required';
                            $rules['mode_remboursement_transfere']='required';
                            $rules['num_paiement_transfere']='required';
                            $rules['pour_le_compte_transfere']='required';
                            if($request->pour_le_compte_transfere=='autre'){
                                //  $rules['fichier_autorisation_transfere']='required';
                            }
                            if($request->mode_rembursement_transfere=='cheque'){
                                // $rules['cheque_recu_transfere']='required';
                             }

                        }
                    }*/
                }
            }
        }
        // dp

        // changement bien

        // penalites
        if ($request->has('checked_penalite') && $request->boolean('checked_penalite')) {
            $rules['penalite_par'] = 'required';
            $rules['mode_penalite'] = 'required';
            if ($request->mode_penalite == 'Montant') {
                $rules['penalite_montant'] = 'required';
            }
            $rules['mode_paiement_pen'] = 'required';
            // mode_paiement cheque/cheque_banque/cheque_certifie/
            if ($request->mode_paiement_pen == 2 || $request->mode_paiement_pen == 3 || $request->mode_paiement_pen == 4) {
                $rules['banque_id_pen'] = 'required';
                $rules['numero_paiement_pen'] = 'required';
                $rules['echeance_pen'] = 'required';
            }
            // virement versement
            elseif ($request->mode_paiement_pen == 5 || $request->mode_paiement_pen == 6) {
                $rules['banque_id_pen'] = 'required';
                $rules['numero_paiement_pen'] = 'required';
            }
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
            // Type
            'type.required' => 'Le champ type de désistement est obligatoire.',

            // Reservation ID
            'reservation_id.required' => 'Le champ réservation est obligatoire.',

            // Type remboursement
            'type_remb.required' => 'Le champ type de remboursement est obligatoire lorsque des avances sont validées.',

            // Dossier ID (transfert)
            'dossier_id.required' => 'Le champ dossier est obligatoire pour un remboursement par transfert.',

            // Pénalités
            'penalite_par.required' => 'Le champ calcul des pénalités est obligatoire.',
            'mode_penalite.required' => 'Le champ mode de pénalité est obligatoire.',
            'penalite_montant.required' => 'Le champ montant de la pénalité est obligatoire.',
            'mode_paiement_pen.required' => 'Le champ mode de paiement des pénalités est obligatoire.',

            // Paiement des pénalités - Chèque
            'banque_id_pen.required' => 'La banque est obligatoire pour ce mode de paiement des pénalités.',
            'numero_paiement_pen.required' => 'Le numéro de paiement des pénalités est obligatoire.',
            'echeance_pen.required' => 'La date d\'échéance des pénalités est obligatoire.',
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
            'type' => 'type de désistement',
            'reservation_id' => 'réservation',
            'type_remb' => 'type de remboursement',
            'dossier_id' => 'dossier',
            'penalite_par' => 'calcul des pénalités',
            'mode_penalite' => 'mode de pénalité',
            'penalite_montant' => 'montant de la pénalité',
            'mode_paiement_pen' => 'mode de paiement des pénalités',
            'banque_id_pen' => 'banque',
            'numero_paiement_pen' => 'numéro de paiement',
            'echeance_pen' => 'date d\'échéance',
        ];
    }
}
