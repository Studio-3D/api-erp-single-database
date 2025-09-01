<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Support\Facades\Auth;

class UpdateImmeubleRequest extends FormRequest
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
            'nom' => [ Rule::unique('temp.'.$DatabaseName.'.immeubles','nom')->whereNull('deleted_at')->where(function ($query) {
                if ($this->bloc_id==null){
                    if ($this->tranche_id==null)
                    {
                        $query->where('nom', $this->nom)
                              ->where('projet_id', $this->projet_id);
                    }
                    else {
                        $query->where('nom', $this->nom)
                        ->where('tranche_id', $this->tranche_id);
                    }
                }

                elseif($this->bloc_id!=null)
                {
                    $query->where('nom', $this->nom)
                        ->where('bloc_id', $this->bloc_id);
                }

                })->ignore($this->immeuble)],


            'tranche_id' => 'integer|nullable',
            'projet_id' => 'integer|nullable',
            'nbre_biens' => 'integer',
            'bloc_id'=>'integer|nullable'

        ];
    }

    public function messages(): array
    {
        if ($this->tranche_id==null && $this->bloc_id==null){
            return [

                'nom.unique' =>  'Cet immeuble existe déjà dans ce projet',
            ];}

        elseif ($this->bloc_id==null) {
            return [

                'nom.unique' =>  'Cet immeuble existe déjà dans cette tranche',
            ];}
        else {
            return [

                'nom.unique' =>  'Cet immeuble existe déjà dans ce bloc',
            ];}

    }
}
