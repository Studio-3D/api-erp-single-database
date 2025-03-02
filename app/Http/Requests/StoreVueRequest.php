<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreVueRequest extends FormRequest
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
        $DatabaseName='Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
        DatabaseHelper::Config();
        return [
            'vue' => ['required', Rule::unique('temp.'.$DatabaseName.'.vues','vue')->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('vue', $this->vue)
                    ->where('projet_id', $this->projet_id);})
        ],
            'projet_id'=>'required|integer'

        ];
    }

    public function messages(): array
    {
        return [
            'vue.unique' => 'Cette vue est deja exist dans ce projet',
        ];
    }
}
