<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'regex:/^[A-Fa-f0-9]{16}$/'], 'device_name' => ['required', 'string', 'max:80']];
    }
}
