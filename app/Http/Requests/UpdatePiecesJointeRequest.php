<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePiecesJointeRequest extends FormRequest
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
            'fichier' => 'required|file|mimes:word,png,jpg,pdf,jpeg',
            'avance_id' => 'integer',
            'reservation_id' => 'integer'
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
            // Fichier
            'fichier.required' => 'Le champ fichier est obligatoire.',
            'fichier.file' => 'Le fichier doit être un fichier valide.',
            'fichier.mimes' => 'Le fichier doit être de type : word, png, jpg, pdf ou jpeg.',

            // Avance ID
            'avance_id.integer' => 'L\'identifiant de l\'avance doit être un nombre entier.',

            // Réservation ID
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
            'fichier' => 'fichier',
            'avance_id' => 'avance',
            'reservation_id' => 'réservation',
        ];
    }
}
