<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Support\Facades\Auth;

class UpdateProjetRequest extends FormRequest
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
            'date_autorisation_construction' => 'date',
            'date_permis_habiter' => 'date|nullable',
            'societe_id' => 'integer',
            'surface_terrain' => 'numeric',
            'prix_acquisition' => 'numeric',
            'limite_annulation_reservation' => 'integer',
            'nbre_tranches' => 'integer',
            'type_id' => 'integer',
            'nbre_blocs' => 'integer',
            'nbre_immeubles' => 'integer',
            'nbre_biens' => 'integer',
            'selectedUsers' => 'required',
            'nom' => ['required', Rule::unique('temp.' . $DatabaseName . '.projets', 'nom')->whereNull('deleted_at')->ignore($this->projet)],
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
            // Date autorisation construction
            'date_autorisation_construction.date' => 'La date d\'autorisation de construction doit être une date valide.',

            // Date permis d'habiter
            'date_permis_habiter.date' => 'La date du permis d\'habiter doit être une date valide.',

            // Société ID
            'societe_id.integer' => 'L\'identifiant de la société doit être un nombre entier.',

            // Surface terrain
            'surface_terrain.numeric' => 'La surface du terrain doit être un nombre.',

            // Prix acquisition
            'prix_acquisition.numeric' => 'Le prix d\'acquisition doit être un nombre.',

            // Limite annulation réservation
            'limite_annulation_reservation.integer' => 'La limite d\'annulation de réservation doit être un nombre entier.',

            // Nombre de tranches
            'nbre_tranches.integer' => 'Le nombre de tranches doit être un nombre entier.',

            // Type ID
            'type_id.integer' => 'L\'identifiant du type doit être un nombre entier.',

            // Nombre de blocs
            'nbre_blocs.integer' => 'Le nombre de blocs doit être un nombre entier.',

            // Nombre d'immeubles
            'nbre_immeubles.integer' => 'Le nombre d\'immeubles doit être un nombre entier.',

            // Nombre de biens
            'nbre_biens.integer' => 'Le nombre de biens doit être un nombre entier.',

            // Utilisateurs sélectionnés
            'selectedUsers.required' => 'Veuillez choisir un utilisateur.',

            // Nom
            'nom.required' => 'Le champ nom du projet est obligatoire.',
            'nom.unique' => 'Ce projet existe déjà dans cette société.',
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
            'date_autorisation_construction' => 'date d\'autorisation de construction',
            'date_permis_habiter' => 'date du permis d\'habiter',
            'societe_id' => 'société',
            'surface_terrain' => 'surface du terrain',
            'prix_acquisition' => 'prix d\'acquisition',
            'limite_annulation_reservation' => 'limite d\'annulation de réservation',
            'nbre_tranches' => 'nombre de tranches',
            'type_id' => 'type de projet',
            'nbre_blocs' => 'nombre de blocs',
            'nbre_immeubles' => 'nombre d\'immeubles',
            'nbre_biens' => 'nombre de biens',
            'selectedUsers' => 'utilisateur',
            'nom' => 'nom du projet',
        ];
    }
}
