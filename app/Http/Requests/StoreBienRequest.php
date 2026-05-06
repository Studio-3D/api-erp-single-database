<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreBienRequest extends FormRequest
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
        $DatabaseName = 'Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
        DatabaseHelper::Config();

        // Règles de base
        $rules = [
            'numero' => ['required', Rule::unique('temp.'.$DatabaseName.'.biens','numero')->whereNull('deleted_at')->where(function ($query) {
                if ($this->immeuble_id==null){
                    if ($this->bloc_id==null){
                        if ($this->tranche_id==null){
                            $query->where('numero', $this->numero)
                            ->where('projet_id', $this->projet_id);
                        }
                        else {
                            $query->where('numero', $this->numero)
                            ->where('tranche_id', $this->tranche_id);
                        }
                    }
                    else{
                        $query->where('numero', $this->numero)
                        ->where('bloc_id', $this->bloc_id);
                    }
                }
                else {
                    $query->where('numero', $this->numero)
                    ->where('immeuble_id', $this->immeuble_id);
                }
            })],
            'orientation' => 'required',
            'prix_unitaire' => 'required|numeric',
            'prix' => 'required|numeric',
            'superficie_architecte' => 'required|numeric',
            'nbre_facades' => 'required|integer',
            'etat' => 'required',
            'type_id' => 'required|integer',
            'projet_id' => 'required|integer',
            'tranche_id' => 'integer|nullable',
            'bloc_id' => 'integer|nullable',
            'avance_minimale' => 'required|numeric',
            'immeuble_id' => 'integer|nullable',
            'propriete_dite_bien' => ['required', Rule::unique('temp.'.$DatabaseName.'.biens','propriete_dite_bien')->whereNull('deleted_at')->where(function ($query) {
                if ($this->immeuble_id==null){
                    if ($this->bloc_id==null){
                        if ($this->tranche_id==null){
                            $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                            ->where('projet_id', $this->projet_id);
                        }
                        else {
                            $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                            ->where('tranche_id', $this->tranche_id);
                        }
                    }
                    else{
                        $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                        ->where('bloc_id', $this->bloc_id);
                    }
                }
                else {
                    $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                    ->where('immeuble_id', $this->immeuble_id);
                }
            })],
            'vue_id' => 'integer|nullable',
            'typologie_id'=> 'integer|nullable',
        ];

        // Rendre niveau requis si bloc_id, tranche_id ou immeuble_id est présent
        if ($this->bloc_id || $this->tranche_id || $this->immeuble_id) {
            $rules['niveau'] = 'required|integer';
        } else {
            $rules['niveau'] = 'nullable';
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
        $messages = [
            // Numero
            'numero.required' => 'Le champ numéro du bien est obligatoire.',

            // Orientation
            'orientation.required' => 'Le champ orientation est obligatoire.',

            // Prix unitaire
            'prix_unitaire.required' => 'Le champ prix unitaire est obligatoire.',
            'prix_unitaire.numeric' => 'Le prix unitaire doit être un nombre.',

            // Prix
            'prix.required' => 'Le champ prix est obligatoire.',
            'prix.numeric' => 'Le prix doit être un nombre.',

            // Superficie architecte
            'superficie_architecte.required' => 'Le champ superficie architecte est obligatoire.',
            'superficie_architecte.numeric' => 'La superficie architecte doit être un nombre.',

            // Nombre de façades
            'nbre_facades.required' => 'Le champ nombre de façades est obligatoire.',
            'nbre_facades.integer' => 'Le nombre de façades doit être un nombre entier.',

            // Etat
            'etat.required' => 'Le champ état du bien est obligatoire.',

            // Type ID
            'type_id.required' => 'Le champ type de bien est obligatoire.',
            'type_id.integer' => 'L\'identifiant du type doit être un nombre entier.',

            // Projet ID
            'projet_id.required' => 'Le champ projet est obligatoire.',
            'projet_id.integer' => 'L\'identifiant du projet doit être un nombre entier.',

            // Avance minimale
            'avance_minimale.required' => 'Le champ avance minimale est obligatoire.',
            'avance_minimale.numeric' => 'L\'avance minimale doit être un nombre.',

            // Niveau
            'niveau.integer' => 'Le niveau (étage) doit être un nombre entier.',

            // Vue ID
            'vue_id.integer' => 'L\'identifiant de la vue doit être un nombre entier.',

            // Typologie ID
            'typologie_id.integer' => 'L\'identifiant de la typologie doit être un nombre entier.',
        ];

        // Messages pour numero.unique
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

        // Message pour niveau requis
        if ($this->bloc_id || $this->tranche_id || $this->immeuble_id) {
            $messages['niveau.required'] = 'Le niveau (étage) est requis pour ce type de bien';
        }

        // Message pour propriete_dite_bien required
        $messages['propriete_dite_bien.required'] = 'Le champ désignation du bien est obligatoire.';

        return $messages;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'numero' => 'numéro du bien',
            'orientation' => 'orientation',
            'prix_unitaire' => 'prix unitaire',
            'prix' => 'prix',
            'superficie_architecte' => 'superficie architecte',
            'nbre_facades' => 'nombre de façades',
            'etat' => 'état du bien',
            'type_id' => 'type de bien',
            'projet_id' => 'projet',
            'tranche_id' => 'tranche',
            'bloc_id' => 'bloc',
            'avance_minimale' => 'avance minimale',
            'immeuble_id' => 'immeuble',
            'propriete_dite_bien' => 'désignation du bien',
            'vue_id' => 'vue',
            'typologie_id' => 'typologie',
            'niveau' => 'niveau (étage)',
        ];
    }
}
