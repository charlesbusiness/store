<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the editable fields of a purchase line.
 *
 * A line's item, unit and declared packs are fixed once written; only the
 * quantity and the prices can be corrected, so this request carries just
 * those. The service re-checks everything that needs the database.
 */
class UpdatePurchaseLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qty.required' => 'Enter a quantity greater than zero.',
            'qty.gt' => 'Enter a quantity greater than zero.',
            'unit_cost.required' => 'Enter the cost for one unit of this line.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'qty' => 'quantity',
            'unit_cost' => 'cost per unit',
            'selling_price' => 'selling price per unit',
        ];
    }
}
