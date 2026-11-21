<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['sources' => ['required', 'array', 'min:1', 'max:6'], 'sources.*' => ['required', 'distinct', Rule::in(config('history.sources'))], 'allow_ip' => ['sometimes', 'boolean'], 'allow_location' => ['sometimes', 'boolean']];
    }
}
