<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitId = $this->route('unit');
        return [
            'code' => ['sometimes', 'required', 'string', 'max:32', 'unique:units,code,' . $unitId],
            'label' => ['sometimes', 'required', 'string', 'max:64'],
            'symbol' => ['nullable', 'string', 'max:16'],
            'plural' => ['nullable', 'string', 'max:64'],
            'family' => ['sometimes', 'required', Rule::in(\App\Enums\UnitFamily::values())],
            'base_multiplier' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'is_fraction' => ['boolean'],
            'is_decimal' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
