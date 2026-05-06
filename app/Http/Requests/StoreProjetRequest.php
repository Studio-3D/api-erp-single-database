<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreProjetRequest extends FormRequest
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
            'nom' => ['required', Rule::unique('temp.' . $DatabaseName . '.projets', 'nom')->whereNull('deleted_at')],
            'code' => 'required|string',
            'adresse' => 'required|string',
            'date_autorisation_construction' => 'required|date',
            'date_permis_habiter' => 'date|nullable',
            'titre_foncier' => 'string|nullable',
            'surface_terrain' => 'required|numeric',
            'prix_acquisition' => 'required|numeric',
            'limite_annulation_reservation' => 'required|integer',
            'type_id' => 'required|integer',
            'nbr_tranches' => 'integer',
            'nbr_blocs' => 'integer',
            'nbr_immeubles' => 'integer',
            'nbr_biens' => 'integer',
            'max_etages' => 'integer',
            'selectedUsers' => 'required',
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
            'nom.required' => 'Le champ nom du projet est obligatoire.',
            'nom.unique' => 'Ce projet existe déjà dans la société.',

            // Code
            'code.required' => 'Le champ code du projet est obligatoire.',
            'code.string' => 'Le code du projet doit être une chaîne de caractères.',

            // Adresse
            'adresse.required' => 'Le champ adresse est obligatoire.',
            'adresse.string' => 'L\'adresse doit être une chaîne de caractères.',

            // Date autorisation construction
            'date_autorisation_construction.required' => 'Le champ date d\'autorisation de construction est obligatoire.',
            'date_autorisation_construction.date' => 'La date d\'autorisation de construction doit être une date valide.',

            // Date permis d'habiter
            'date_permis_habiter.date' => 'La date du permis d\'habiter doit être une date valide.',

            // Titre foncier
            'titre_foncier.string' => 'Le titre foncier doit être une chaîne de caractères.',

            // Surface terrain
            'surface_terrain.required' => 'Le champ surface du terrain est obligatoire.',
            'surface_terrain.numeric' => 'La surface du terrain doit être un nombre.',

            // Prix acquisition
            'prix_acquisition.required' => 'Le champ prix d\'acquisition est obligatoire.',
            'prix_acquisition.numeric' => 'Le prix d\'acquisition doit être un nombre.',

            // Limite annulation réservation
            'limite_annulation_reservation.required' => 'Le champ limite d\'annulation de réservation est obligatoire.',
            'limite_annulation_reservation.integer' => 'La limite d\'annulation de réservation doit être un nombre entier.',

            // Type ID
            'type_id.required' => 'Le champ type de projet est obligatoire.',
            'type_id.integer' => 'L\'identifiant du type doit être un nombre entier.',

            // Nombre de tranches
            'nbr_tranches.integer' => 'Le nombre de tranches doit être un nombre entier.',

            // Nombre de blocs
            'nbr_blocs.integer' => 'Le nombre de blocs doit être un nombre entier.',

            // Nombre d'immeubles
            'nbr_immeubles.integer' => 'Le nombre d\'immeubles doit être un nombre entier.',

            // Nombre de biens
            'nbr_biens.integer' => 'Le nombre de biens doit être un nombre entier.',

            // Nombre d'étages maximum
            'max_etages.integer' => 'Le nombre maximum d\'étages doit être un nombre entier.',

            // Utilisateurs sélectionnés
            'selectedUsers.required' => 'Veuillez choisir un utilisateur.',
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
            'nom' => 'nom du projet',
            'code' => 'code du projet',
            'adresse' => 'adresse',
            'date_autorisation_construction' => 'date d\'autorisation de construction',
            'date_permis_habiter' => 'date du permis d\'habiter',
            'titre_foncier' => 'titre foncier',
            'surface_terrain' => 'surface du terrain',
            'prix_acquisition' => 'prix d\'acquisition',
            'limite_annulation_reservation' => 'limite d\'annulation de réservation',
            'type_id' => 'type de projet',
            'nbr_tranches' => 'nombre de tranches',
            'nbr_blocs' => 'nombre de blocs',
            'nbr_immeubles' => 'nombre d\'immeubles',
            'nbr_biens' => 'nombre de biens',
            'max_etages' => 'nombre maximum d\'étages',
            'selectedUsers' => 'utilisateur',
        ];
    }
}
