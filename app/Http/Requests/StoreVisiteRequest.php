<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[AllowDynamicProperties] class StoreVisiteRequest extends FormRequest
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
        $rules['telephone'] = 'required|min:10|max:14';
        $rules['source_id'] = 'required';
        //$rules['nom']='required|string';
        $rules['prenom'] = 'required|string';
        $rules['interet'] = 'required';
        $rules['telephone_num2'] = 'min:10|max:14|nullable';

        if ($request->source_txt === 'PARTENAIRE') {
            $rules['partenaire_id'] = 'required';
        }
        //interesse
        if ($request->interet == 1) {
            //multiple
            //$rules['bien_id']='required';
            //$rules['statut']='required';
            $rules['cin'] = 'required';
        }
        //perdu
        elseif ($request->interet == 3) {
            $rules['frein'] = 'required';

        }

        return $rules;

    }
}
