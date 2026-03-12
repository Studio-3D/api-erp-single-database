<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            //'name' => 'required|string',
           // 'prenom' => 'required|string',
            'email' => [
                'required',
                'email',
                'max:254',
                // Check uniqueness across all users
                Rule::unique('users', 'email')->where(function ($query) {
                    return $query->whereNotNull('email');
                }),
            ],
            'password' => 'required|min:6|same:password_confirmation',
            'password_confirmation' => 'required|min:6',
            'role' => 'required|integer',
            'phone' => 'string|min:10|max:14|nullable',
            'photo' => 'image|mimes:png,jpg,jpeg|max:2048|nullable',
            'cin' => 'string|unique:users,cin,NULL,id,deleted_at,NULL|nullable',
            'date_embauche' => 'date|nullable',
            'cnss' => 'integer|nullable',
            'is_actif' => 'integer|nullable',
            // 'nb_appel_recu' => 'integer',
            // 'nb_appel_traite' => 'integer',
            'solde_conge' => 'integer|nullable',
            'societe_id' => 'required|integer',

        ];
    }
    public function messages(): array
    {
        return [
            'cin.unique' => 'Le Cin que vous avez saisi apprtient à un autre utilisateur',
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre utilisateur. Veuillez choisir une autre adresse email.',
            'email.required' => 'L\'email est requis',
            'email.email' => 'Veuillez entrer une adresse email valide',
            'societe_id.required' => 'la societe est requise',
        ];
    }
}
