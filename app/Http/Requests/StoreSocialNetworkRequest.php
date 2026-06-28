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
        $rules = [
            'description' => 'required|string',
            'mode' => 'required|in:parcourir,existante,sans_media', // Fixed: Added 'sans_media'
            'reseaux_sociaux' => 'required|string',
            'projet_id' => 'required|integer|exists:projets,id', // Added exists validation
        ];

        // Conditional validation based on mode
        if ($request->mode == 'existante') {
            $rules['img_existant_url'] = 'required|url';
        } elseif ($request->mode == 'sans_media') {
            // No media required for text-only mode
            // Optionally validate media_type if provided
            if ($request->has('media_type')) {
                $rules['media_type'] = 'in:text,image,video';
            }
        } else {
            // parcourir mode - requires a media file
            $rules['mediaFile'] = 'required|file|max:10240'; // 10MB max
        }

        // Conditionally add the 'required' rule to 'phoneNumber' if 'reseaux_sociaux' contains whatsapp
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
            'description.string' => 'La description doit être une chaîne de caractères.',

            // Mode
            'mode.required' => 'Le champ mode est obligatoire.',
            'mode.in' => 'Le mode doit être : "parcourir", "existante" ou "sans_media".',

            // Réseaux sociaux
            'reseaux_sociaux.required' => 'Le champ réseau social est obligatoire.',
            'reseaux_sociaux.string' => 'Le réseau social doit être une chaîne de caractères.',

            // Projet ID
            'projet_id.required' => 'Le champ projet est obligatoire.',
            'projet_id.integer' => 'L\'identifiant du projet doit être un nombre entier.',
            'projet_id.exists' => 'Le projet spécifié n\'existe pas.',

            // Image existante
            'img_existant_url.required' => 'L\'URL de l\'image existante est obligatoire pour le mode "existante".',
            'img_existant_url.url' => 'L\'URL de l\'image existante doit être une URL valide.',

            // Fichier média
            'mediaFile.required' => 'Le fichier média est obligatoire pour le mode "parcourir".',
            'mediaFile.file' => 'Le fichier média doit être un fichier valide.',
            'mediaFile.max' => 'Le fichier média ne doit pas dépasser 10 Mo.',

            // Media type
            'media_type.in' => 'Le type de média doit être : "text", "image" ou "video".',

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
            'media_type' => 'type de média',
            'phoneNumber' => 'numéro de téléphone',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // If mode is 'sans_media' and media_type is not set, default to 'text'
        if ($this->input('mode') === 'sans_media' && !$this->has('media_type')) {
            $this->merge([
                'media_type' => 'text'
            ]);
        }
    }
}
