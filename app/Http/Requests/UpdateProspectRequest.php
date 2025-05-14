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
        $DatabaseName='Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
        DatabaseHelper::Config();
        return [
            'telephone_num2' => 'nullable|min:10|max:14', // Optional telephone_num2 field
            'telephone' => [
                'required', // Telephone is required
                'min:10',   // Minimum length of 10
                'max:14',   // Maximum length of 14
                Rule::unique('temp.' . $DatabaseName . '.prospects', 'telephone') // Unique check on telephone column
                    ->whereNull('deleted_at') // Only check non-deleted records
                    ->ignore($this->prospect) // Ignore the current record for updates
            ],
            'cin' => [ 'nullable',Rule::unique('temp.' . $DatabaseName . '.prospects', 'cin')->whereNull('deleted_at')->ignore($this->prospect)],
            'email' => [ 'nullable',Rule::unique('temp.' . $DatabaseName . '.prospects', 'email')->whereNull('deleted_at')->ignore($this->prospect)],
        ];
    }

    public function messages(): array
    {
        return [
            'cin.unique' => 'Le cin appartient à un autre utilisateur',
            'email.unique' => 'L\'email appartient à un autre utilisateur',
        ];
    }
}
