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
    {   $societe_id = Auth::guard('api')->user()->societe_id;
        $societe=Societe::findOrfail( $societe_id);
        $DatabaseName='Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
        DatabaseHelper::Config();
        return [

            'nom' => ['required', Rule::unique('temp.'.$DatabaseName.'.tranches','nom')->whereNull('deleted_at')->where(function ($query) {
                $query->where('nom', $this->nom)
                    ->where('projet_id', $this->projet_id);})],
            'date_lancement' => 'date|nullable',
            'date_livraison' => 'date|nullable',
            'niveau_etages' => 'integer|nullable',
            'nbre_blocs' => 'integer',
            'nbre_immeubles' => 'integer',
            'nbre_biens' => 'integer',
            'projet_id'=>'required|integer'
        ];
    }

    public function messages(): array
    {
        return [
            'nom.unique' => 'Ce tranche est deja exist dans ce projet',
        ];
    }
}
