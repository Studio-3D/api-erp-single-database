<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvanceRequest extends FormRequest
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
            "montant"=>"double",
            "date_reglement"=>"date",
            "mode_paiement"=>"integer",
            "echeance"=>"date",
            "sr"=>"boolean",
            "banque_id"=>"integer",
            "numero_paiemeant"=>"integer",
            "reservation_id"=>"integer",
        ];
    }
}
