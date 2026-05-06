<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAquereurRequest extends FormRequest
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
            "pourcentage" => "required|integer",
            "client_id" => "required|integer",
            "reservation_id" => "required|integer",
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
            // Pourcentage
            'pourcentage.required' => 'Le champ pourcentage est obligatoire.',
            'pourcentage.integer' => 'Le pourcentage doit être un nombre entier.',

            // Client ID
            'client_id.required' => 'Le champ client est obligatoire.',
            'client_id.integer' => 'L\'identifiant du client doit être un nombre entier.',

            // Reservation ID
            'reservation_id.required' => 'Le champ réservation est obligatoire.',
            'reservation_id.integer' => 'L\'identifiant de la réservation doit être un nombre entier.',
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
            'pourcentage' => 'pourcentage',
            'client_id' => 'client',
            'reservation_id' => 'réservation',
        ];
    }
}
