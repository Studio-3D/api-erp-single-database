<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
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
            "nb_acquereurs"=>"integer",
            "code_reservation"=>"string",
            "prix"=>"integer",
            "mode_financement"=>"required",
            "date_reservation"=>"date",
            "date_limite_reservation"=>"date",
            //"visite_id"=>"integer",
            "bien_id" => "integer",
        ];
    }
}
