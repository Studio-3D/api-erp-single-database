<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImmeubleRequest extends FormRequest
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
        $societe_id = Auth::guard('api')->user()->societe_id;
        $societe = Societe::findOrfail($societe_id);
        $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
        DatabaseHelper::Config();

        return [
            'nom' => ['required', Rule::unique('temp.' . $DatabaseName . '.immeubles', 'nom')->whereNull('deleted_at')->where(function ($query) {
                if ($this->bloc_id == null) {
                    if ($this->tranche_id == null) {
                        $query->where('nom', $this->nom)
                            ->where('projet_id', $this->projet_id);
                    } else {
                        $query->where('nom', $this->nom)
                            ->where('tranche_id', $this->tranche_id);
                    }
                } else {
                    $query->where('nom', $this->nom)
                        ->where('bloc_id', $this->bloc_id);
                }
            })],
            'tranche_id' => 'integer|nullable',
            'projet_id' => 'required|integer',
            'nbre_biens' => 'integer',
            'bloc_id' => 'integer|nullable'
        ];
    }

    /**
     * Get the validation error messages in French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            // Nom
            'nom.required' => 'Le champ nom de l\'immeuble est obligatoire.',

            // Projet ID
            'projet_id.required' => 'Le champ projet est obligatoire.',
            'projet_id.integer' => 'L\'identifiant du projet doit être un nombre entier.',

            // Tranche ID
            'tranche_id.integer' => 'L\'identifiant de la tranche doit être un nombre entier.',

            // Bloc ID
            'bloc_id.integer' => 'L\'identifiant du bloc doit être un nombre entier.',

            // Nombre de biens
            'nbre_biens.integer' => 'Le nombre de biens doit être un nombre entier.',
        ];

        // Messages conditionnels pour nom.unique
        if ($this->tranche_id == null && $this->bloc_id == null) {
            $messages['nom.unique'] = 'Cet immeuble existe déjà dans ce projet';
        } elseif ($this->bloc_id == null) {
            $messages['nom.unique'] = 'Cet immeuble existe déjà dans cette tranche';
        } else {
            $messages['nom.unique'] = 'Cet immeuble existe déjà dans ce bloc';
        }

        return $messages;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nom' => 'nom de l\'immeuble',
            'projet_id' => 'projet',
            'tranche_id' => 'tranche',
            'bloc_id' => 'bloc',
            'nbre_biens' => 'nombre de biens',
        ];
    }
}
