<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateTrancheRequest extends FormRequest
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
    {$societe_id = Auth::guard('api')->user()->societe_id;
        $societe = Societe::findOrfail($societe_id);
        $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
        DatabaseHelper::Config();
        return [
            'projet_id' => 'integer',
            'date_lancement' => 'date|nullable',
            'date_livraison' => 'date|nullable',
            'niveau_etages' => 'integer|nullable',
            'nbre_blocs' => 'integer ',
            'nbre_immeubles' => 'integer',
            'nbre_biens' => 'integer',
            'nom' => [Rule::unique('temp.' . $DatabaseName . '.tranches', 'nom')->where(function ($query) {
                $query->where('nom', $this->nom)
                    ->where('projet_id', $this->projet_id);})->ignore($this->tranche)],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.unique' => 'ce tranche est deje exist dans ce projet',
        ];
    }
}
