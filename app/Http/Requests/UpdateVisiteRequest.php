<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisiteRequest extends FormRequest
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
            'commentaire' => 'string|min:6',
            'source_id' => 'integer',
            'notifie' => 'boolean',
            'type_notification'=>'integer',
            'interet' => 'required|integer',
            'mode_relance' => 'integer',
            'date_relance' => 'date',
            'rdv' => 'datetime',
            'statut' => 'string',
            'bien_id'=>'integer',
            'prix_min'=>'float',
            'prix_max'=>'float',
            'superficie_min'=>'float',
            'superficie_max'=>'float',
            'liste_attente'=>'boolean',
            'avance'=>'float'
        ];
    }
}
