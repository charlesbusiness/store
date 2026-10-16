<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the editable fields of a sale line.
 *
 * A sale line's item, unit and declared packs are fixed once written; only the
 * quantity and the selling price can be corrected. Cost is not entered here:
 * a sale's cost is the average it was bought for, snapshotted on write, so it
 * is scaled from the stored line rather than typed in.
 */
class UpdateSaleLineRequest extends FormRequest
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
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
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
            'unit_price.required' => 'Enter the selling price for one unit of this line.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'qty' => 'quantity',
            'unit_price' => 'selling price per unit',
        ];
    }
}
