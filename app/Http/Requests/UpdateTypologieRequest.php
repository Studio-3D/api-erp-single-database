<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTypologieRequest extends FormRequest
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
            'typologie'=>['required',Rule::unique('temp.'.$DatabaseName.'.typologies','typologie')
                ->where('projet_id',$this->typologie->projet_id)
                ->ignore($this->typologie)],
            'projet_id'=>'required|integer',
        ];
    }
    public function messages(): array
    {
        return [
            'typologie.unique' => 'Cette typologie existe déjà dans ce projet.',
        ];
    }
}
