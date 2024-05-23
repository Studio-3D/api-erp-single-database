<?php

namespace App\Http\Requests;
use App\Models\Societe;
use Illuminate\Validation\Rule;

use App\Http\Helpers\DatabaseHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StorePartenaireRequest extends FormRequest
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
        $DatabaseName='Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
        DatabaseHelper::Config();
        return [
            'description'=>['required',Rule::unique('temp.'.$DatabaseName.'.partenaires','description')
                ->where('projet_id',$this->projet_id)
                ],
            'remise'=>'integer',
            'projet_id'=>'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'description.unique' => 'le Partenaire que vous avez saisie existe déja dans ce projet',
        ];
    }
}
