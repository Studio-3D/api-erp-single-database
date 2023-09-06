<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateProspectRequest extends FormRequest
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
        $societe=Societe::findOrfail( $societe_id);
        $DatabaseName='Erp_'.$societe->raison_sociale.'_'.$societe_id;
        DatabaseHelper::Config();
        return [
            'nom' => 'required|string',
            'prenom' => 'required|string',
            'telephone' => 'required|string',
            'telephone_num2' => 'string',
            'email'=>'string',
            'source'=>'string',
            'cin' => ['required', Rule::unique('temp.'.$DatabaseName.'.prospects','cin')->ignore($this->prospect)],
        ];
    }

    public function messages(): array
    {
        return [
            'cin.unique' => 'Ce prospect existe déjà dans cette société.',
        ];
    }
}
