<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlocRequest extends FormRequest
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
            'nom' => 'required',
            'titre_foncier' => 'required',
            'projet_id' => 'required|integer',
            'tranche_id' => 'integer',
            'nbre_immeubles' => 'integer',
            'nbre_biens' => 'integer',
        ];
    }
}
