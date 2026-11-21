<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MfaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']];
    }
}
