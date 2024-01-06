<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
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
            "type_client"=>"string",
            "prenom"=>"required|string",
            "telephone_num1"=>"required|string",
            "telephone_num2"=>"string",
            "notifie"=>"integer",
            "email"=>"string",
            "civilite"=>"integer",
            "adresse"=>"string",
            "ville"=>"string",
            "pays"=>"string",
            "profession"=>"string",
            "cin"=>"required|string",
            "lieu_naissance"=>"string",
            "nationalite"=>"string",
            "date_naissance"=>"date",
            "age"=>"integer",
            "nom_responsable"=>"string",
            "relation_familliale"=>"string",
            "situation_familliale"=>"integer",
            "nom_pere"=>"string",
            "nom_mere"=>"string",
            "nom_mari"=>"string",
            "lieu_mariage"=>"string",
            "date_mariage"=>"date",
            "nom"=>"required|string ",

        ];
    }
}
