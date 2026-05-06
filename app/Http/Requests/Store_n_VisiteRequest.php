<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

#[AllowDynamicProperties]
class Store_n_VisiteRequest extends FormRequest
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
    public function rules(Request $request): array
    {
        $rules = [];
        $rules['interet'] = 'required';

        // interessé
        if ($request->interet == 1){
            // $rules['bien_id'] = 'required';
            // $rules['statut'] = 'required';
            // $rules['cin'] = 'required';
        }
        // perdu
        elseif ($request->interet == 3){
            $rules['frein'] = 'required';
        }

        return $rules;
    }

    /**
     * Get the validation error messages in French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'interet.required' => 'Le champ intérêt est obligatoire.',
            'frein.required' => 'Le champ frein est obligatoire lorsque le client n\'est pas intéressé.',
            // Add other validation messages as needed
            // 'bien_id.required' => 'Le champ bien est obligatoire.',
            // 'statut.required' => 'Le champ statut est obligatoire.',
            // 'cin.required' => 'Le champ CIN est obligatoire.',
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
            'interet' => 'intérêt',
            'frein' => 'frein',
            'bien_id' => 'bien',
            'statut' => 'statut',
            'cin' => 'CIN',
        ];
    }
}
