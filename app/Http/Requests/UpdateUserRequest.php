<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'name' => 'string',
            'prenom' => 'string',
            'role' => 'integer|nullable',
            'phone' => 'string|min:10|max:14|nullable',
            'photo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048|nullable',
            'date_embauche' => 'date|nullable',
            'cnss' => 'integer|nullable',
            'is_actif' => 'integer|nullable',
            'solde_conge' => 'integer|nullable',
            'password' => 'min:6|nullable',
            //rien ne va changer avec cette validation
            /* 'cin' => [
                Rule::unique('users')->ignore($this->user),
            ], */

        ];
    }

    public function messages(): array
    {
        return [
            'cin.unique' => 'Le Cin que vous avez saisi apprtient à un autre utilisateur',
        ];
    }
}
