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
    {   $societe_id = Auth::guard('api')->user()->societe_id;
        $societe=Societe::findOrfail( $societe_id);
        $DatabaseName='Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
        DatabaseHelper::Config();
        return [
            'projet_id' => 'integer',
            'tranche_id' => 'integer|nullable',
            'nbre_immeubles' => 'integer',
            'nbre_biens' => 'integer',
            'nom' => [ Rule::unique('temp.'.$DatabaseName.'.blocs','nom')->whereNull('deleted_at')->where(function ($query) {
                if ($this->tranche_id==null){
                    $query->where('nom', $this->nom)
                    ->where('projet_id', $this->projet_id);
                }
                else {
                    $query->where('nom', $this->nom)
                    ->where('tranche_id', $this->tranche_id);
                }



            })->ignore($this->bloc)],

        ];
    }

    public function messages(): array
    {   if ($this->tranche_id==null){
            return [

            'nom.unique' =>  'ce nom de bloc existe déjà dans ce projet',
            ];
        }

        else {
            return [

                'nom.unique' =>  'ce nom de bloc existe déjà dans cette tranche',
            ];
        }
    }
}
