<?php

namespace App\Http\Requests;


use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

#[AllowDynamicProperties] class StoreAppelRequest extends FormRequest
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
        $rules['telephone']='required|min:10|max:14';
        $rules['source']='required';
        $rules['type_appel']='required';
        $rules['interet']='required';
      //  $rules['commentaire']='required';
        $rules['projet_id']='required';

        if ($request->telephone_num2!="null" && $request->telephone_num2!=null ) {
            $rules['telephone_num2']='min:10|max:14';
        }
        if ($request->source_txt==='PARTENAIRE'||$request->source_txt==='Partenaire') {
            $rules['partenaire_id']='required';
        }
       if ($request->interet == 3){
           //perdu
            $rules['freins']='required';
        }elseif ($request->interet == 1){
            //interesse
            $rules['type_biens']='required';
            $rules['orientation']='required';
        }

        return $rules;


    }
}
