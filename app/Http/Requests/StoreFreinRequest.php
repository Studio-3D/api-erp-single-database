<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFreinRequest extends FormRequest
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
            'prix_min'=>'float',
            'prix_max'=>'float',
            'superficie_min'=>'float',
            'superficie_max'=>'float',
            'liste_attente'=>'boolean',
            'avance'=>'float',
            'visite_id'=>'required|integer',
        ];
    }
}
