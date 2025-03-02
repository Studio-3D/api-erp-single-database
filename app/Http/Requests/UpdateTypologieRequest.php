<?php

namespace App\Http\Requests;

use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateTypologieRequest extends FormRequest
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
        return [
            'typologie' => ['required', Rule::unique('temp.' . $DatabaseName . '.typologies', 'typologie')->whereNull('deleted_at')
                    ->where(function ($query) {
                        $query->where('typologie', $this->typologie)
                            ->where('projet_id', $this->projet_id);}),
            ],
            'projet_id' => 'integer',

        ];
    }
    public function messages(): array
    {
        return [
            'typologie.unique' => 'Cette typologie existe déjà dans ce projet.',
        ];
    }
}
