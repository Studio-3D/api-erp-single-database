<?php

namespace App\Http\Requests;

use App\Models\Societe;
use Illuminate\Validation\Rule;
use App\Http\Helpers\DatabaseHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePartenaireRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function rules(): array
    {
        $societe_id = Auth::guard('api')->user()->societe_id;
        $societe=Societe::findOrfail( $societe_id);
        $DatabaseName='Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
        DatabaseHelper::Config();
        return [
            'description' => [Rule::unique('temp.' . $DatabaseName . '.partenaires', 'description')->whereNull('deleted_at')->where(function ($query) {
                $query->where('description', $this->description)
                    ->where('projet_id', $this->projet_id);})->ignore($this->partenaire)],
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
