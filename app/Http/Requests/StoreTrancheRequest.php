<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTrancheRequest extends FormRequest
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
            'nom' => ['required', Rule::unique('temp.' . $DatabaseName . '.tranches', 'nom')->whereNull('deleted_at')->where(function ($query) {
                $query->where('nom', $this->nom)
                    ->where('projet_id', $this->projet_id);
            })],
            'date_lancement' => 'date|nullable',
            'date_livraison' => 'date|nullable',
            'niveau_etages' => 'integer|nullable',
            'nbre_blocs' => 'integer',
            'nbre_immeubles' => 'integer',
            'nbre_biens' => 'integer',
            'projet_id' => 'required|integer'
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
            // Nom
            'nom.required' => 'Le champ nom de la tranche est obligatoire.',
            'nom.unique' => 'Cette tranche existe déjà dans ce projet.',

            // Date lancement
            'date_lancement.date' => 'La date de lancement doit être une date valide.',

            // Date livraison
            'date_livraison.date' => 'La date de livraison doit être une date valide.',

            // Niveau étages
            'niveau_etages.integer' => 'Le niveau des étages doit être un nombre entier.',

            // Nombre de blocs
            'nbre_blocs.integer' => 'Le nombre de blocs doit être un nombre entier.',

            // Nombre d'immeubles
            'nbre_immeubles.integer' => 'Le nombre d\'immeubles doit être un nombre entier.',

            // Nombre de biens
            'nbre_biens.integer' => 'Le nombre de biens doit être un nombre entier.',

            // Projet ID
            'projet_id.required' => 'Le champ projet est obligatoire.',
            'projet_id.integer' => 'L\'identifiant du projet doit être un nombre entier.',
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
            'nom' => 'nom de la tranche',
            'date_lancement' => 'date de lancement',
            'date_livraison' => 'date de livraison',
            'niveau_etages' => 'niveau des étages',
            'nbre_blocs' => 'nombre de blocs',
            'nbre_immeubles' => 'nombre d\'immeubles',
            'nbre_biens' => 'nombre de biens',
            'projet_id' => 'projet',
        ];
    }
}
