<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

#[AllowDynamicProperties]
class StoreDecompteRequest extends FormRequest
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
    public function rules(Request $request): array
    {
        $rules = [];
        $rules['numero'] = 'required';
        $rules['montant'] = 'required';
        $rules['date'] = 'required';
        return $rules;
    }

    /**
     * Get the validation error messages in French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Numéro
            'numero.required' => 'Le champ numéro du décompte est obligatoire.',

            // Montant
            'montant.required' => 'Le champ montant est obligatoire.',

            // Date
            'date.required' => 'Le champ date est obligatoire.',
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
            'numero' => 'numéro du décompte',
            'montant' => 'montant',
            'date' => 'date',
        ];
    }
}
