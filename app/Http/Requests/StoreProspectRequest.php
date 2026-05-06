<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreProspectRequest extends FormRequest
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
            'telephone' => 'required',
            'cin' => ['nullable', Rule::unique('temp.' . $DatabaseName . '.prospects', 'cin')->whereNull('deleted_at')],
            'email' => ['nullable', Rule::unique('temp.' . $DatabaseName . '.prospects', 'email')->whereNull('deleted_at')],
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
            // Téléphone
            'telephone.required' => 'Le champ numéro de téléphone est obligatoire.',

            // CIN
            'cin.unique' => 'Ce CIN appartient déjà à un autre prospect.',

            // Email
            'email.unique' => 'Cet email appartient déjà à un autre prospect.',
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
            'telephone' => 'numéro de téléphone',
            'cin' => 'CIN',
            'email' => 'email',
        ];
    }
}
