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
        $societe=Societe::findOrfail( $societe_id);
        $DatabaseName='Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
        DatabaseHelper::Config();
        return [
            'nb_acquereurs' => 'required|integer',
            //'code_reservation' => 'required|string',
            'prix' => 'required',
            'mode_financement' => 'required',
            'date_reservation'=>'date',
            'date_limite_reservation'=>'date',
            'code_reservation' => ['string', Rule::unique('temp.'.$DatabaseName.'.reservations','code_reservation')->ignore($this->reservation)],
            'bien_id' => 'integer',

        ];
    }
}
