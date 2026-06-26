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
        $DatabaseName = env('DB_DATABASE');
        DatabaseHelper::Config();

        $table = 'temp.' . $DatabaseName . '.prospects';

        return [
            'telephone' => [
                'required',
                Rule::unique($table, 'telephone')
                    ->whereNull('deleted_at')
            ],
            'telephone_num2' => [
                'nullable',
                // 🔥 Unicité seulement si la valeur n'est pas null ou vide
                Rule::unique($table, 'telephone_num2')
                    ->whereNull('deleted_at')
                    ->where(function ($query) {
                        $query->whereNotNull('telephone_num2')
                              ->where('telephone_num2', '!=', '');
                    })
            ],
            'cin' => [
                'nullable',
                Rule::unique($table, 'cin')
                    ->whereNull('deleted_at')
            ],
            'email' => [
                'nullable',
                Rule::unique($table, 'email')
                    ->whereNull('deleted_at')
            ],
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
            'telephone.unique' => 'Ce numéro de téléphone appartient déjà à un autre prospect.',

            // Téléphone 2
            'telephone_num2.unique' => 'Ce numéro de téléphone secondaire appartient déjà à un autre prospect.',

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
            'telephone_num2' => 'numéro de téléphone secondaire',
            'cin' => 'CIN',
            'email' => 'email',
        ];
    }
}
