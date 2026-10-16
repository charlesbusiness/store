<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'unique:units,code'],
            'label' => ['required', 'string', 'max:64'],
            'symbol' => ['nullable', 'string', 'max:16'],
            'plural' => ['nullable', 'string', 'max:64'],
            'family' => ['required', Rule::in(\App\Enums\UnitFamily::values())],
            'base_multiplier' => ['required', 'numeric', 'gt:0'],
            'is_fraction' => ['boolean'],
            'is_decimal' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
