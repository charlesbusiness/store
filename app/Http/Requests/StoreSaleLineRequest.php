<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates one or more lines being appended to an existing sale.
 *
 * A sale may only draw on stock that already exists, so every line must name an
 * item id; there is no free-text name here the way a purchase has one. The
 * shape checks live here; whether the item can be measured in that unit and
 * whether there is enough stock belong to the services, which run inside the
 * write transaction.
 */
class StoreSaleLineRequest extends FormRequest
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
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.name' => ['prohibited'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'lines.*.unit_code' => ['required', 'string', Rule::exists('units', 'code')->where('is_active', true)],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one item to the sale.',
            'lines.*.unit_price.required' => 'Enter the selling price for one unit of this line.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lines.*.unit_code' => 'unit',
            'lines.*.unit_price' => 'selling price per unit',
            'lines.*.unit_cost' => 'cost per unit',
        ];
    }

    /**
     * The rows the record writer expects.
     *
     * @return array{lines: array<int, array<string, mixed>>}
     */
    public function payload(): array
    {
        return [
            'lines' => array_map(
                fn (array $line) => [
                    'item_id' => $line['item_id'] ?? null,
                    'qty' => $line['qty'] ?? null,
                    'unit_code' => $line['unit_code'] ?? null,
                    'unit_price' => $line['unit_price'] ?? null,
                    'unit_cost' => $line['unit_cost'] ?? null,
                ],
                $this->validated('lines') ?? []
            ),
        ];
    }
}
