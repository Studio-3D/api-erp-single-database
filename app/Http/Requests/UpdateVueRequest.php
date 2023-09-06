<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateVueRequest extends FormRequest
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
            'vue'=>['required',Rule::unique('temp.'.$DatabaseName.'.vues','vue')
                ->where('projet_id',$this->vue->projet_id)
                ->ignore($this->vue)],
            'projet_id'=>'required|integer',
        ];
    }
    public function messages(): array
    {
        return [
            'vue.unique' => 'Cette vue existe déjà dans ce projet.',
        ];
    }
}
