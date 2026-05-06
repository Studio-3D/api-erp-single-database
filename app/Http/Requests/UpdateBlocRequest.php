<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Support\Facades\Auth;

class UpdateBlocRequest extends FormRequest
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
            'projet_id' => 'integer',
            'tranche_id' => 'integer|nullable',
            'nbre_immeubles' => 'integer',
            'nbre_biens' => 'integer',
            'nom' => [Rule::unique('temp.' . $DatabaseName . '.blocs', 'nom')->whereNull('deleted_at')->where(function ($query) {
                if ($this->tranche_id == null) {
                    $query->where('nom', $this->nom)
                        ->where('projet_id', $this->projet_id);
                } else {
                    $query->where('nom', $this->nom)
                        ->where('tranche_id', $this->tranche_id);
                }
            })->ignore($this->bloc)],
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
            // Projet ID
            'projet_id.integer' => 'L\'identifiant du projet doit être un nombre entier.',

            // Tranche ID
            'tranche_id.integer' => 'L\'identifiant de la tranche doit être un nombre entier.',

            // Nombre d'immeubles
            'nbre_immeubles.integer' => 'Le nombre d\'immeubles doit être un nombre entier.',

            // Nombre de biens
            'nbre_biens.integer' => 'Le nombre de biens doit être un nombre entier.',
        ];

        // Messages conditionnels pour nom.unique
        if ($this->tranche_id == null) {
            $messages['nom.unique'] = 'Ce bloc existe déjà dans ce projet.';
        } else {
            $messages['nom.unique'] = 'Ce bloc existe déjà dans cette tranche.';
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
            'projet_id' => 'projet',
            'tranche_id' => 'tranche',
            'nbre_immeubles' => 'nombre d\'immeubles',
            'nbre_biens' => 'nombre de biens',
            'nom' => 'nom du bloc',
        ];
    }
}
