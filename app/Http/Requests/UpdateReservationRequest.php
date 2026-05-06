<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateReservationRequest extends FormRequest
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
            'nb_acquereurs' => 'required|integer',
            // 'code_reservation' => 'required|string',
            'prix' => 'required',
            'mode_financement' => 'required',
            'date_reservation' => 'date',
            'date_limite_reservation' => 'date',
            // 'code_reservation' => ['string', Rule::unique('temp.'.$DatabaseName.'.reservations','code_reservation')->ignore($this->reservation)],
            'bien_id' => 'integer',
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
            // Nombre d'acquéreurs
            'nb_acquereurs.required' => 'Le champ nombre d\'acquéreurs est obligatoire.',
            'nb_acquereurs.integer' => 'Le nombre d\'acquéreurs doit être un nombre entier.',

            // Prix
            'prix.required' => 'Le champ prix est obligatoire.',

            // Mode financement
            'mode_financement.required' => 'Le champ mode de financement est obligatoire.',

            // Date réservation
            'date_reservation.date' => 'La date de réservation doit être une date valide.',

            // Date limite réservation
            'date_limite_reservation.date' => 'La date limite de réservation doit être une date valide.',

            // Bien ID
            'bien_id.integer' => 'L\'identifiant du bien doit être un nombre entier.',
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
            'nb_acquereurs' => 'nombre d\'acquéreurs',
            'prix' => 'prix',
            'mode_financement' => 'mode de financement',
            'date_reservation' => 'date de réservation',
            'date_limite_reservation' => 'date limite de réservation',
            'bien_id' => 'bien',
        ];
    }
}
