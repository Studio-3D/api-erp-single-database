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
    { $societe_id = Auth::guard('api')->user()->societe_id;
        $societe = Societe::findOrfail($societe_id);
        $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
        DatabaseHelper::Config();
        return [
            'niveau' => 'integer',
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
            'propriete_dite_bien' => ['string',Rule::unique('temp.' . $DatabaseName . '.biens', 'propriete_dite_bien')->whereNull('deleted_at')->where(function ($query) {
                if ($this->immeuble_id == null) {
                    if ($this->bloc_id == null) {
                        if ($this->tranche_id == null) {
                            $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                                ->where('projet_id', $this->projet_id);
                        } else { $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                                ->where('tranche_id', $this->tranche_id);}

                    } else {
                        $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                            ->where('bloc_id', $this->bloc_id);
                    }
                } else { $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                        ->where('immeuble_id', $this->immeuble_id);
                }
            })->ignore($this->bien)],
            'vue_id'=> 'integer|nullable',
            'typologie_id'=> 'integer|nullable',
        ];
    }

    public function messages(): array

    {if ($this->tranche_id == null && $this->bloc_id == null && $this->immeuble_id == null) {
        return [

            'propriete_dite_bien.unique' => 'Ce bien est deja exist dans ce projet',
        ];
    } elseif ($this->immeuble_id == null && $this->bloc_id == null) {
        return [

            'propriete_dite_bien.unique' => 'Ce bien est deja exist dans ce tranche',
        ];
    } elseif ($this->immeuble_id == null) {
        return [

            'propriete_dite_bien.unique' => 'Ce bien est deja exist dans ce bloc',
        ];
    } else {
        return [

            'propriete_dite_bien.unique' => 'Ce bien est deja exist dans cet emmeuble',
        ];
    }
    }
}
