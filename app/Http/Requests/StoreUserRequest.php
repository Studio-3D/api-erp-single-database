<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
            'name' => 'required|string',
            'prenom' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|integer',
            'phone' => 'string|min:10|max:14',
            'photo' => 'image|mimes:png,jpg,jpeg|max:2048',
            'cin' => 'string|unique:users',
            'date_embauche' => 'date',
            'cnss' => 'integer',
            'is_actif' => 'integer',
            'nb_appel_recu' => 'integer',
            'nb_appel_traite' => 'integer',
            'solde_conge' => 'integer', 
        
        ];
    }
}
