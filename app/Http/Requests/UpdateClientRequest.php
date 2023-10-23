<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
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
            "type_client"=>"integer ",
            "nom"=>"required|string ",
            "prenom"=>"required|string",
            "telephone_num1"=>"required|string",
            "telephone_num2"=>"string",
            "notifie"=>"boolean",
            "email"=>"string",
            "civilite"=>"integer",
            "adresse"=>"string",
            "ville"=>"string",
            "pays"=>"string",
            "profession"=>"string",
            'cin' => ['required', Rule::unique('temp.'.$DatabaseName.'.clients','cin')->ignore($this->client)],
            "lieu_naissance"=>"string",
            "nationalite"=>"string",
            "date_naissance"=>"date",
            "age"=>"integer",
            "nom_responsable"=>"string",
            "relation_familliale"=>"string",
            "situation_familliale"=>"integer",
            "nom_pere"=>"string",
            "nom_mere"=>"string",
        ];
    }
    public function messages(): array
    {
        return [
            'cin.unique' => 'Ce cin existe déjà dans la table des clients',
        ];
    }
}
