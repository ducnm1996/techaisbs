<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DeviceOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['sources' => ['required', 'array', 'min:1', 'max:7'], 'sources.*' => ['required', 'distinct', Rule::in(config('history.sources'))], 'allow_ip' => ['sometimes', 'boolean'], 'allow_location' => ['sometimes', 'boolean']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && in_array('network', $this->input('sources'), true) && ! array_intersect(['zalo', 'viber', 'email'], $this->input('sources'))) {
                $validator->errors()->add('sources', 'VPN / IP đích cần chọn thêm ít nhất một nguồn Zalo, Viber hoặc email.');
            }
        });
    }
}
