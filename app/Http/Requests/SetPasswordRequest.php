<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:254'], 'token' => ['required', 'string', 'max:200'], 'password' => ['required', 'confirmed', Password::min(14)->letters()->numbers()->mixedCase(), 'max:200']];
    }
}
