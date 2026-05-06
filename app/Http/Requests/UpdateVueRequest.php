<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateVueRequest extends FormRequest
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
        // Récupération société et config base comme tu fais
        $societe_id = Auth::guard('api')->user()->societe_id;
        $societe = Societe::findOrFail($societe_id);
        $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
        DatabaseHelper::Config();

        // Récupérer l'ID ou l'instance du modèle depuis la route
        $id = $this->route('vue'); // 'vue' correspond au paramètre de route (à adapter selon ta route)

        if (is_object($id)) {
            $id = $id->id;
        }

        return [
            'vue' => [
                'required',
                Rule::unique('temp.' . $DatabaseName . '.vues', 'vue')
                    ->whereNull('deleted_at')
                    ->where('projet_id', $this->projet_id)
                    ->ignore($id),
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
            // Vue
            'vue.required' => 'Le champ vue est obligatoire.',
            'vue.unique' => 'Cette vue existe déjà dans ce projet.',

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
            'vue' => 'vue',
            'projet_id' => 'projet',
        ];
    }
}
