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
    {   $societe_id = Auth::guard('api')->user()->societe_id;
        $societe=Societe::findOrfail( $societe_id);
        $DatabaseName='Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
        DatabaseHelper::Config();
        return [
            'numero' => 'required',
            'niveau' => 'required|integer',
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
            'propriete_dite_bien' => ['required', Rule::unique('temp.'.$DatabaseName.'.biens','propriete_dite_bien')->where(function ($query) {
                        if ($this->immeuble_id==null){
                            if ($this->bloc_id==null){
                                if ($this->tranche_id==null){
                                    $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                                    ->where('projet_id', $this->projet_id);
                                }
                                else {$query->where('propriete_dite_bien', $this->propriete_dite_bien)
                                    ->where('tranche_id', $this->tranche_id);}
                                }
                            else{
                                $query->where('propriete_dite_bien', $this->propriete_dite_bien)
                                ->where('bloc_id', $this->bloc_id);
                            }
                        }
                        else {$query->where('propriete_dite_bien', $this->propriete_dite_bien)
                                ->where('immeuble_id', $this->immeuble_id);
                        }
                        })],
            'vue_id' => 'integer|nullable',
            'typologie_id'=> 'integer|nullable',


        ];
    }

    public function messages(): array

        {   if ($this->tranche_id==null && $this->bloc_id==null && $this->immeuble_id==null){
                return [

                'propriete_dite_bien.unique' =>  'Ce bien existe déjà dans ce projet',
            ];
            }

            elseif ($this->immeuble_id==null && $this->bloc_id==null) {
                return [

                'propriete_dite_bien.unique' =>  'Ce bien existe déjà dans ce tranche',
            ];
            }
            elseif ($this->immeuble_id==null ) {
            return [

                'propriete_dite_bien.unique' =>  'Ce bien existe déjà dans ce bloc',
            ];
            }

            else {
                return [

                    'propriete_dite_bien.unique' =>  'Ce bien existe déjà dans cet immeuble',
                ];
                }
        }
}


