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
        $societe = Societe::findOrfail($societe_id);
        $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
        DatabaseHelper::Config();

        return [
            "type_client" => "required|string",
            "prenom" => "required|string",
            "telephone_num1" => "required|min:10|max:14",
            "telephone_num2" => "nullable|min:10|max:14",
            "notifie" => "integer",
            "date_naissance" => "date|nullable",
            "age" => "integer|nullable",
            "date_mariage" => "date|nullable",
            "situation_familliale" => "required|string",
            "civilite" => "required|string",
            'cin' => ['required', Rule::unique('temp.' . $DatabaseName . '.clients', 'cin')->whereNull('deleted_at')->ignore($this->client)],
        ];
    }

    /**
     * Get the validation error messages in French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Type client
            'type_client.required' => 'Le champ type de client est obligatoire.',
            'type_client.string' => 'Le type de client doit être une chaîne de caractères.',

            // Prénom
            'prenom.required' => 'Le champ prénom est obligatoire.',
            'prenom.string' => 'Le prénom doit être une chaîne de caractères.',

            // Téléphone numéro 1
            'telephone_num1.required' => 'Le numéro de téléphone principal est obligatoire.',
            'telephone_num1.min' => 'Le numéro de téléphone principal doit contenir au moins :min caractères.',
            'telephone_num1.max' => 'Le numéro de téléphone principal ne peut pas dépasser :max caractères.',

            // Téléphone numéro 2
            'telephone_num2.min' => 'Le deuxième numéro de téléphone doit contenir au moins :min caractères.',
            'telephone_num2.max' => 'Le deuxième numéro de téléphone ne peut pas dépasser :max caractères.',

            // Notifié
            'notifie.integer' => 'Le champ notifié doit être un nombre entier.',

            // Date de naissance
            'date_naissance.date' => 'La date de naissance doit être une date valide.',

            // Âge
            'age.integer' => 'L\'âge doit être un nombre entier.',

            // Date de mariage
            'date_mariage.date' => 'La date de mariage doit être une date valide.',

            // Situation familiale
            'situation_familliale.required' => 'Le champ situation familiale est obligatoire.',
            'situation_familliale.string' => 'La situation familiale doit être une chaîne de caractères.',

            // Civilité
            'civilite.required' => 'Le champ civilité est obligatoire.',
            'civilite.string' => 'La civilité doit être une chaîne de caractères.',

            // CIN
            'cin.required' => 'Le champ CIN est obligatoire.',
            'cin.unique' => 'Ce CIN existe déjà dans la table des clients.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type_client' => 'type de client',
            'prenom' => 'prénom',
            'telephone_num1' => 'numéro de téléphone principal',
            'telephone_num2' => 'deuxième numéro de téléphone',
            'notifie' => 'notifié',
            'date_naissance' => 'date de naissance',
            'age' => 'âge',
            'date_mariage' => 'date de mariage',
            'situation_familliale' => 'situation familiale',
            'civilite' => 'civilité',
            'cin' => 'CIN',
        ];
    }
}
