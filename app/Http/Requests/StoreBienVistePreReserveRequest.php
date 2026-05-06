<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBienVistePreReserveRequest extends FormRequest
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
            'visite_id' => 'required',
            'bien_id' => 'required',
            'code_pre_reserve' => 'required',
            'date_pre_reserve' => 'required',
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
            // Visite ID
            'visite_id.required' => 'Le champ visite est obligatoire.',

            // Bien ID
            'bien_id.required' => 'Le champ bien est obligatoire.',

            // Code pré-réservation
            'code_pre_reserve.required' => 'Le champ code de pré-réservation est obligatoire.',

            // Date pré-réservation
            'date_pre_reserve.required' => 'Le champ date de pré-réservation est obligatoire.',
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
            'visite_id' => 'visite',
            'bien_id' => 'bien',
            'code_pre_reserve' => 'code de pré-réservation',
            'date_pre_reserve' => 'date de pré-réservation',
        ];
    }
}
