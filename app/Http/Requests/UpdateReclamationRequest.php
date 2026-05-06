<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[AllowDynamicProperties]
class UpdateReclamationRequest extends FormRequest
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
        $rules['bien_id'] = 'required';
        $rules['client_id'] = 'required';
        $rules['date_reclamation'] = 'required';
        $rules['problemes'] = 'required';

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
            // Bien ID
            'bien_id.required' => 'Le champ bien est obligatoire.',

            // Client ID
            'client_id.required' => 'Le champ client est obligatoire.',

            // Date réclamation
            'date_reclamation.required' => 'Le champ date de réclamation est obligatoire.',

            // Problèmes
            'problemes.required' => 'Le champ description des problèmes est obligatoire.',
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
            'bien_id' => 'bien',
            'client_id' => 'client',
            'date_reclamation' => 'date de réclamation',
            'problemes' => 'description des problèmes',
        ];
    }
}
