<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transacted_on' => ['required', 'date_format:Y-m-d'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'lines.*.unit_code' => ['required', 'string', Rule::exists('units,code')->where('is_active', true)],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.total' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.inner_units' => ['nullable', 'array'],
            'lines.*.inner_units.*.unit' => ['required_with:lines.*.inner_units.*.per', 'string', Rule::exists('units,code')->where('is_active', true)],
            'lines.*.inner_units.*.per' => ['required_with:lines.*.inner_units.*.unit', 'integer', 'min:1', 'max:100000'],
            'lines.*.inner_units.*.cost' => ['required_with:lines.*.inner_units.*.unit', 'nullable', 'numeric', 'min:0'],
            'lines.*.inner_units.*.sellingPrice' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
