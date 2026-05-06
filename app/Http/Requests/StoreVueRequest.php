<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreVueRequest extends FormRequest
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
        $societe = Societe::findOrfail($societe_id);
        $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
        DatabaseHelper::Config();

        return [
            'vue' => ['required', Rule::unique('temp.' . $DatabaseName . '.vues', 'vue')->whereNull('deleted_at')
                ->where(function ($query) {
                    $query->where('vue', $this->vue)
                        ->where('projet_id', $this->projet_id);
                })],
            'projet_id' => 'required|integer'
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
            'projet_id.required' => 'Le champ projet est obligatoire.',
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
