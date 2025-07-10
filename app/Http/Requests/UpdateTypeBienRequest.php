<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Http\Helpers\DatabaseHelper;
use App\Models\Societe;
use Illuminate\Support\Facades\Auth;

class UpdateTypeBienRequest extends FormRequest
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
    $societe = Societe::findOrFail($societe_id);
    $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;
    DatabaseHelper::Config();

    $id = $this->route('typeBien'); // Récupère l'ID ou l'instance

    // Si c'est une instance, récupérer l'ID
    if (is_object($id)) {
        $id = $id->id;
    }

    return [
        'type' => [
            'required',
            Rule::unique('temp.' . $DatabaseName . '.type_biens', 'type')
                ->whereNull('deleted_at')
                ->where(function ($query) {
                    $query->where('type', $this->type)
                          ->where('projet_id', $this->projet_id);
                })
                ->ignore($id), // Ignore l'enregistrement courant
        ],
        'projet_id' => 'integer',
    ];
}

    public function messages(): array
    {
        return [
            'type.unique' => 'Ce type de bien est deja exist dans ce projet',
        ];
    }
}
