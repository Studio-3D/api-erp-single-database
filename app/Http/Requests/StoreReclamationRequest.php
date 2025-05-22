<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[AllowDynamicProperties] class StoreReclamationRequest extends FormRequest
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
        $rules['bien_id'] = 'required';
        $rules['client_id'] = 'required';
        $rules['service_id'] = 'required';
        $rules['date_reclamation'] = 'required';
        $rules['problemes'] = 'required';
        return $rules;

    }
}
