<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFreinRequest extends FormRequest
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
            'prix_min' => 'float',
            'prix_max' => 'float',
            'superficie_min' => 'float',
            'superficie_max' => 'float',
            'avance' => 'float',
            'visite_id' => 'required|integer',
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
            // Prix minimum
            'prix_min.float' => 'Le champ prix minimum doit être un nombre décimal.',

            // Prix maximum
            'prix_max.float' => 'Le champ prix maximum doit être un nombre décimal.',

            // Superficie minimum
            'superficie_min.float' => 'Le champ superficie minimum doit être un nombre décimal.',

            // Superficie maximum
            'superficie_max.float' => 'Le champ superficie maximum doit être un nombre décimal.',

            // Avance
            'avance.float' => 'Le champ avance doit être un nombre décimal.',

            // Visite ID
            'visite_id.required' => 'Le champ visite est obligatoire.',
            'visite_id.integer' => 'L\'identifiant de la visite doit être un nombre entier.',
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
            'prix_min' => 'prix minimum',
            'prix_max' => 'prix maximum',
            'superficie_min' => 'superficie minimum',
            'superficie_max' => 'superficie maximum',
            'avance' => 'avance',
            'visite_id' => 'visite',
        ];
    }
}
