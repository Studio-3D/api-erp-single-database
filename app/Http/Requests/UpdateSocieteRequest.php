<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use \Illuminate\Validation\Rule;

class UpdateSocieteRequest extends FormRequest
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
           // 'raison_sociale' => 'min:3',
            'raison_sociale' => [
                'min:3',
                Rule::unique('societes')->ignore($this->societe)
            ],  
            'nom_contact' => 'min:3',
            'tel' => 'string|min:10|max:14',
            'email' => 'email',
            'logo' => 'image|mimes:png,jpg,jpeg|max:2048',      
        ];
    }
}
