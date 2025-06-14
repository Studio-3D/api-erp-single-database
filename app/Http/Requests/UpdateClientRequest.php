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
        $societe_id   = Auth::guard('api')->user()->societe_id;
        $societe      = Societe::findOrfail($societe_id);
        $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
        DatabaseHelper::Config();
        return [
            "type_client"          => "required|string",
            "prenom"               => "required|string",
            "telephone_num1"       => "required|min:10|max:14",
            "telephone_num2"       => "nullable|min:10|max:14",
            "notifie"              => "integer",
            "date_naissance"       => "date|nullable",
            "age"                  => "integer|nullable",
            "date_mariage"         => "date|nullable",
            "situation_familliale" => "required",
            "civilite"             => "required",
            'cin'                  => ['required', Rule::unique('temp.' . $DatabaseName . '.clients', 'cin')->whereNull('deleted_at')->ignore($this->client)],

        ];
    }
    public function messages(): array
    {
        return [
            'cin.unique' => 'Ce cin existe déjà dans la table des clients',
        ];
    }
}
