<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;
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
        $societe=Societe::findOrfail( $societe_id);
        $DatabaseName='Erp_'.$societe->raison_sociale.'_'.$societe_id;
        DatabaseHelper::Config();
        return [
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
            'nom' => ['required', Rule::unique('temp.'.$DatabaseName.'.projets','nom')],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.unique' => 'Ce projet est deja exist dans la societe',
            'selectedUsers.required' => 'Veuillez choisissez un utilisateur',
        ];
    }
}
