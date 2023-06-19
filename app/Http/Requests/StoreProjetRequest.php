<?php

namespace App\Http\Requests;

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
        return [
            'nom' => 'required',
            'code' => 'required|string',
            'adresse' => 'required|string',
            'date_autorisation_construction' => 'required|date',
            'date_permis_habiter' => 'required|date',
            'titre_foncier' => 'required|string',
            'surface_terrain' => 'required|numeric',
            'prix_acquisition' => 'required|numeric',
            'limite_annulation_reservation' => 'required|integer',
            'nbr_tranches' => 'integer',
            'nbr_blocs' => 'integer',
            'nbr_immeubles' => 'integer',
            'nbr_biens' => 'integer',
            'societe_id' => 'required',
           /*  'nom' => ["required",Rule::unique('projets','nom')->where('societe_id', $this->input('societe_id'))
            ],  */
            
        ];
    }
}
