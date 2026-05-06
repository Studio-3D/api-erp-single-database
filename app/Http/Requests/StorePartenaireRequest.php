<?php

namespace App\Http\Requests;

use App\Models\Societe;
use Illuminate\Validation\Rule;
use App\Http\Helpers\DatabaseHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StorePartenaireRequest extends FormRequest
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
            'description' => ['required', Rule::unique('temp.' . $DatabaseName . '.partenaires', 'description')->whereNull('deleted_at')
                ->where('projet_id', $this->projet_id)],
            'remise' => 'integer|nullable',
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
            // Description
            'description.required' => 'Le champ description du partenaire est obligatoire.',
            'description.unique' => 'Le partenaire que vous avez saisi existe déjà dans ce projet.',

            // Remise
            'remise.integer' => 'Le champ remise doit être un nombre entier.',
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
            'description' => 'description du partenaire',
            'remise' => 'remise',
            'projet_id' => 'projet',
        ];
    }
}
