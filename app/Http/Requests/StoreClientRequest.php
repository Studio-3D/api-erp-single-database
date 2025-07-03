<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
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
            "type_client" => "required|string",
            "prenom" => "required|string",
            "telephone_num1" => "required|min:10|max:14",
            "telephone_num2" => "nullable|min:10|max:14",
            "notifie" => "integer",
            "cin" => "required",
            "date_naissance" => "date|nullable",
            "age" => "integer|nullable",
            "date_mariage" => "date|nullable",
            "situation_familliale" => "required|string",
            "civilite" => "required|string",

        ];
    }
}
