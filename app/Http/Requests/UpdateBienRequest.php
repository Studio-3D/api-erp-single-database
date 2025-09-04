<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateBienRequest extends FormRequest
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

        // Règles de base
        $rules = [
            'numero' => ['integer', Rule::unique('temp.' . $DatabaseName . '.biens', 'numero')->whereNull('deleted_at')->where(function ($query) {
                if ($this->immeuble_id == null) {
                    if ($this->bloc_id == null) {
                        if ($this->tranche_id == null) {
                            $query->where('numero', $this->numero)
                                ->where('projet_id', $this->projet_id);
                        } else {
                            $query->where('numero', $this->numero)
                                ->where('tranche_id', $this->tranche_id);
                        }
                    } else {
                        $query->where('numero', $this->numero)
                            ->where('bloc_id', $this->bloc_id);
                    }
                } else {
                    $query->where('numero', $this->numero)
                        ->where('immeuble_id', $this->immeuble_id);
                }
            })->ignore($this->bien)],
            'prix_unitaire' => 'numeric',
            'avance_minimale' => 'numeric',
            'prix' => 'numeric',
            'superficie_habitable' => 'numeric|nullable',
            'nbre_facades' => 'integer',
            'superficie_parking' => 'numeric|nullable',
            'superficie_architecte' => 'numeric',
            'superficie_box' => 'numeric|nullable',
            'superficie_terrasse' => 'numeric|nullable',
            'superficie_jardin' => 'numeric|nullable',
            'type_id' => 'integer',
            'projet_id' => 'integer',
            'tranche_id' => 'integer|nullable',
            'bloc_id' => 'integer|nullable',
            'immeuble_id' => 'integer|nullable',
            'propriete_dite_bien' => ['string', Rule::unique('temp.' . $DatabaseName . '.biens', 'propriete_dite_bien')->whereNull('deleted_at')->where(function ($query) {
                if ($this->immeuble_id == null) {
                    if ($this->bloc_id == null) {
                        if ($this->tranche_id == null) {
                            $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                                ->where('projet_id', $this->projet_id);
                        } else {
                            $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                                ->where('tranche_id', $this->tranche_id);
                        }
                    } else {
                        $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                            ->where('bloc_id', $this->bloc_id);
                    }
                } else {
                    $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                        ->where('immeuble_id', $this->immeuble_id);
                }
            })->ignore($this->bien)],
            'vue_id' => 'integer|nullable',
            'typologie_id' => 'integer|nullable',
        ];

        // Règles conditionnelles pour le champ niveau
        if ($this->bloc_id || $this->tranche_id || $this->immeuble_id) {
            // Si un des IDs parent est présent, niveau est requis mais peut être null ou vide
            $rules['niveau'] = 'required|integer|nullable';
        } else {
            // Sinon, niveau est optionnel
            $rules['niveau'] = 'nullable';
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [];

        // Messages pour numero.unique et propriete_dite_bien.unique
        if ($this->tranche_id == null && $this->bloc_id == null && $this->immeuble_id == null) {
            $messages['numero.unique'] = 'Ce numéro de bien existe déjà dans ce projet';
            $messages['propriete_dite_bien.unique'] = 'Ce bien existe déjà dans ce projet';
        } elseif ($this->immeuble_id == null && $this->bloc_id == null) {
            $messages['numero.unique'] = 'Ce numéro de bien existe déjà dans cette tranche';
            $messages['propriete_dite_bien.unique'] = 'Ce bien existe déjà dans cette tranche';
        } elseif ($this->immeuble_id == null) {
            $messages['numero.unique'] = 'Ce numéro de bien existe déjà dans ce bloc';
            $messages['propriete_dite_bien.unique'] = 'Ce bien existe déjà dans ce bloc';
        } else {
            $messages['numero.unique'] = 'Ce numéro de bien existe déjà dans cet immeuble';
            $messages['propriete_dite_bien.unique'] = 'Ce bien existe déjà dans cet immeuble';
        }

        // Ajouter le message d'erreur pour niveau si nécessaire
        if ($this->bloc_id || $this->tranche_id || $this->immeuble_id) {
            $messages['niveau.required'] = 'Le niveau (étage) est requis pour ce type de bien';
            $messages['niveau.integer'] = 'Le niveau doit être un nombre entier';
        }

        return $messages;
    }
}
