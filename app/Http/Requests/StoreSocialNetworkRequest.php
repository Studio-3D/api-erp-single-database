<?php

namespace App\Http\Requests;


use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

#[AllowDynamicProperties] class StoreSocialNetworkRequest extends FormRequest
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
        $rules['description']='required';
        $rules['mode']='required';
        $rules['reseaux_sociaux']='required';


        if ($request->mode=='existante' ) {
            $rules['img_existant_url']='required';
        }else{
            //parcourir
            $rules['mediaFile']='required';
        }
        // Conditionally add the 'required' rule to 'num_telephone' if 'reseaux_sociaux' contains whatsapp
        if (strpos($request->reseaux_sociaux, '1') !== false) {
            $rules['phoneNumber'] = 'required|regex:/^\+\d{1,4}\d{6,9}$/'; // Corrected '||' to '|'

        }


        return $rules;


    }
}
