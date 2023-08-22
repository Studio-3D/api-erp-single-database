<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProspectRequest extends FormRequest
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
            'cin' => 'string',
            'nom' => 'required|string',
            'prenom' => 'required|string',
            'telephone' => 'required|string',
            'telephone_num2' => 'string',
            'email'=>'string',
            'source'=>'string'
        ];
    }
}
