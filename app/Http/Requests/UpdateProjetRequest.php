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
    {   $societe_id = Auth::guard('api')->user()->societe_id;
        $societe=Societe::findOrfail( $societe_id);
        $DatabaseName='Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
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
            'nom' => ['required', Rule::unique('temp.'.$DatabaseName.'.projets','nom')->whereNull('deleted_at')->ignore($this->projet)],
        ];
    }

    public function messages(): array
    {
        return [

            'nom.unique' => 'Ce projet est deja exist dans cette societe',
            'selectedUsers.required' => 'Veuillez choisissez un utilisateur',
        ];
    }
}
