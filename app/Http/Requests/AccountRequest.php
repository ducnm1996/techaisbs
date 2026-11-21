<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80'], 'email' => ['required', 'email', 'max:254', 'unique:users,email'], 'role' => ['required', Rule::in(['admin', 'member'])]];
    }
}
