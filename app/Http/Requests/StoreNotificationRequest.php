<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
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
            'type' => 'required',
            'description_type' => 'required',
            'lien' => 'required',
            'user_id' => 'required',
        ];
    }

    /**
     * Get the validation error messages in French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Type
            'type.required' => 'Le champ type de notification est obligatoire.',

            // Description type
            'description_type.required' => 'Le champ description du type est obligatoire.',

            // Lien
            'lien.required' => 'Le champ lien est obligatoire.',

            // Utilisateur ID
            'user_id.required' => 'Le champ utilisateur est obligatoire.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'type de notification',
            'description_type' => 'description du type',
            'lien' => 'lien',
            'user_id' => 'utilisateur',
        ];
    }
}
