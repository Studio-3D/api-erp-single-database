<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

#[AllowDynamicProperties]
class StoreSocialNetworkRequest extends FormRequest
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
        $rules['description'] = 'required';
        $rules['mode'] = 'required';
        $rules['reseaux_sociaux'] = 'required';
        $rules['projet_id'] = 'required|integer'; // Add projet_id validation

        if ($request->mode == 'existante') {
            $rules['img_existant_url'] = 'required';
        } else {
            // parcourir
            $rules['mediaFile'] = 'required';
        }

        // Conditionally add the 'required' rule to 'num_telephone' if 'reseaux_sociaux' contains whatsapp
        if (strpos($request->reseaux_sociaux, '1') !== false) {
            $rules['phoneNumber'] = 'required|regex:/^\+\d{1,4}\d{6,9}$/';
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
            // Description
            'description.required' => 'Le champ description est obligatoire.',

            // Mode
            'mode.required' => 'Le champ mode est obligatoire.',

            // Réseaux sociaux
            'reseaux_sociaux.required' => 'Le champ réseau social est obligatoire.',

            // Projet ID
            'projet_id.required' => 'Le champ projet est obligatoire.',
            'projet_id.integer' => 'L\'identifiant du projet doit être un nombre entier.',

            // Image existante
            'img_existant_url.required' => 'L\'URL de l\'image existante est obligatoire pour le mode "existante".',

            // Fichier média
            'mediaFile.required' => 'Le fichier média est obligatoire pour le mode "parcourir".',

            // Numéro de téléphone (WhatsApp)
            'phoneNumber.required' => 'Le numéro de téléphone est obligatoire pour WhatsApp.',
            'phoneNumber.regex' => 'Le numéro de téléphone doit être au format international (ex: +212612345678).',
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
            'description' => 'description',
            'mode' => 'mode',
            'reseaux_sociaux' => 'réseau social',
            'projet_id' => 'projet',
            'img_existant_url' => 'URL de l\'image existante',
            'mediaFile' => 'fichier média',
            'phoneNumber' => 'numéro de téléphone',
        ];
    }
}
