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
            'role' => 'integer',
            'phone' => 'string|min:10|max:14|nullable',
            'photo' => 'image|mimes:png,jpg,jpeg|max:2048',
           /* 'cin' => [
                Rule::unique('users')->ignore($this->user),
            ],*/
            'date_embauche' => 'date',
            'cnss' => 'integer|nullable',
            'is_actif' => 'integer',
            'solde_conge' => 'integer|nullable',
            'password' => 'min:6|nullable',


        ];
    }
}
