<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePiecesJointeRequest extends FormRequest
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
        return [
            'fichier'=>'required|file|mimes:word,png,jpg,pdf,jpeg|max:2048',
            'avance_id'=>'integer',
            'reservation_id'=>'integer'
        ];
    }
}
