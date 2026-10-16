<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160', 'unique:items,name'],
            'family' => ['required', Rule::in(\App\Enums\UnitFamily::values())],
            'default_unit_code' => ['required', 'string', 'exists:units,code'],
        ];
    }
}
