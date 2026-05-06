<?php

namespace App\Http\Requests;

use App\Models\Societe;
use Illuminate\Validation\Rule;
use App\Http\Helpers\DatabaseHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreSourceRequest extends FormRequest
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
            'source' => ['required', Rule::unique('temp.' . $DatabaseName . '.sources', 'source')->whereNull('deleted_at')],
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
            // Source
            'source.required' => 'Le champ source est obligatoire.',
            'source.unique' => 'La source que vous avez saisie existe déjà.',
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
            'source' => 'source',
        ];
    }
}
