<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $itemId = $this->route('item');
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160', Rule::unique('items', 'name')->ignore($itemId)],
            'family' => ['sometimes', 'required', Rule::in(\App\Enums\UnitFamily::values())],
            'default_unit_code' => ['sometimes', 'required', 'string', 'exists:units,code'],
        ];
    }
}
