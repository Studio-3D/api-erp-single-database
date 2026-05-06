<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateTypologieRequest extends FormRequest
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
        $societe_id = Auth::guard('api')->user()->societe_id;
        $societe = Societe::findOrFail($societe_id);
        $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
        DatabaseHelper::Config();

        // Récupérer l'ID directement (pas d'objet)
        $id = $this->route('typologie');

        return [
            'typologie' => [
                'required',
                Rule::unique('temp.' . $DatabaseName . '.typologies', 'typologie')
                    ->whereNull('deleted_at')
                    ->where('projet_id', $this->projet_id)
                    ->ignore($id),  // Ici on passe directement l'ID
            ],
            'projet_id' => 'integer',
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
            // Typologie
            'typologie.required' => 'Le champ typologie est obligatoire.',
            'typologie.unique' => 'Cette typologie existe déjà dans ce projet.',

            // Projet ID
            'projet_id.integer' => 'L\'identifiant du projet doit être un nombre entier.',
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
            'typologie' => 'typologie',
            'projet_id' => 'projet',
        ];
    }
}
