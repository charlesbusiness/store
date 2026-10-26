<?php

namespace App\Http\Requests;

use App\Enums\RecordType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a submitted cart of lines for either tab.
 *
 * Both tabs state a price for one unit of the line and let the total fall out of
 * the multiplication, so one rule covers both.
 *
 * The shape checks live here; the rules that need the database -- whether the
 * item can be measured in that unit, whether a declared pack makes sense, whether
 * there is enough stock -- belong to the services, which run inside the write
 * transaction.
 */
class RecordTransactionRequest extends FormRequest
{
    /** Maximum number of inner unit declarations per line. */
    private const MAX_INNER_UNITS = 10;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function type(): RecordType
    {
        return $this->route('type') === RecordType::Sale->value
            ? RecordType::Sale
            : RecordType::Purchase;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'transacted_on' => ['required', 'date_format:Y-m-d'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => $this->type()->allowsNewItems()
                ? ['nullable', 'integer']
                : ['required', 'integer'],
            'lines.*.name' => $this->type()->allowsNewItems()
                ? ['required', 'string', 'max:160']
                : ['prohibited'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'lines.*.unit_code' => ['required', 'string', Rule::exists('units', 'code')->where('is_active', true)],
            ...$this->packRules(),
        ];

        if ($this->type() === RecordType::Purchase) {
            // Purchases: unit_cost is required (becomes the effective price)
            $rules['lines.*.unit_cost'] = ['required', 'numeric', 'min:0'];
            // Optional reference selling price carried from the buy form to the sell form.
            $rules['lines.*.selling_price'] = ['nullable', 'numeric', 'min:0', 'max:999999999'];
            // unit_price is not required for purchases - it will be set from unit_cost
        } else {
            // Sales: unit_price (selling price) is required
            $rules['lines.*.unit_price'] = ['required', 'numeric', 'min:0', 'max:999999999'];
            // unit_cost is optional for sales (read-only display from ledger)
            $rules['lines.*.unit_cost'] = ['nullable', 'numeric', 'min:0'];
            $rules['customer_name'] = ['nullable', 'string', 'max:120'];
        }

        return $rules;
    }

    /**
     * A purchase may declare what one trading unit is made of. A sale never
     * declares: it reads what the receipt that supplied the stock declared.
     *
     * @return array<string, mixed>
     */
    private function packRules(): array
    {
        return [
            'lines.*.inner_units' => ['nullable', 'array', 'max:'.self::MAX_INNER_UNITS],
            'lines.*.inner_units.*.unit' => [
                'nullable', 'string', Rule::exists('units', 'code')->where('is_active', true),
            ],
            'lines.*.inner_units.*.per' => [
                'nullable', 'integer', 'min:1', 'max:100000',
            ],
            'lines.*.inner_units.*.cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.inner_units.*.sellingPrice' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $selling = $this->type() === RecordType::Sale;

        return [
            'transacted_on.required' => 'Choose a date for this record.',
            'lines.required' => $selling
                ? 'Add at least one item to the sale.'
                : 'Add at least one item to the purchase.',
            'lines.*.name.required' => 'Name the item you are buying.',
            'lines.*.unit_price.required' => 'Enter the selling price for one unit of this line.',
            'lines.*.unit_cost.required' => 'Enter the cost for one unit of this line.',
            'lines.*.inner_units.*.per.integer' => 'Enter a whole number of these per unit.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'transacted_on' => 'date',
            'customer_name' => 'customer name',
            'lines.*.unit_code' => 'unit',
            'lines.*.unit_price' => 'selling price per unit',
            'lines.*.unit_cost' => 'cost per unit',
            'lines.*.selling_price' => 'selling price per unit',
            'lines.*.inner_units' => 'inner units',
            'lines.*.inner_units.*.unit' => 'inner unit',
            'lines.*.inner_units.*.per' => 'inner unit quantity',
            'lines.*.inner_units.*.cost' => 'inner unit cost',
            'lines.*.inner_units.*.sellingPrice' => 'inner unit selling price',
        ];
    }

    /**
     * The payload the record writer expects.
     *
     * @return array{transacted_on: string, customer_name: string|null, lines: array<int, array<string, mixed>>}
     */
    public function payload(): array
    {
        $isPurchase = $this->type() === RecordType::Purchase;

        return [
            'transacted_on' => $this->date('transacted_on')->toDateString(),
            'customer_name' => $isPurchase ? null : $this->customerName(),
            'lines' => array_map(
                fn (array $line) => [
                    'item_id' => $line['item_id'] ?? null,
                    'name' => $line['name'] ?? null,
                    'qty' => $line['qty'] ?? null,
                    'unit_code' => $line['unit_code'] ?? null,
                    // For purchases: unit_price = unit_cost (cost is the effective price)
                    // Fall back to unit_price if unit_cost missing (form sends both for purchases)
                    'unit_price' => $isPurchase
                        ? ($line['unit_cost'] ?? $line['unit_price'] ?? null)
                        : ($line['unit_price'] ?? null),
                    'unit_cost' => $line['unit_cost'] ?? ($isPurchase ? $line['unit_price'] : null),
                    'selling_price' => $line['selling_price'] ?? null,
                    'inner_units' => $this->declaredPacks($line),
                ],
                $this->validated('lines') ?? []
            ),
        ];
    }

    private function customerName(): ?string
    {
        $name = trim((string) ($this->validated('customer_name') ?? ''));

        return $name === '' ? null : $name;
    }

    /**
     * Just the pack pairs that were actually filled in, so a blank slot does not
     * travel as a null the normaliser has to unpick.
     *
     * @param  array<string, mixed>  $line
     * @return array<int, array{unit: string, per: float, cost: float|null, sellingPrice: float|null}>
     */
    private function declaredPacks(array $line): array
    {
        $pairs = [];

        foreach ((array) ($line['inner_units'] ?? []) as $slot) {
            if (! is_array($slot) || ($slot['unit'] ?? '') === '' || ($slot['per'] ?? '') === '') {
                continue;
            }

            $cost = ($slot['cost'] ?? '') !== '' ? (float) $slot['cost'] : null;
            $sellingPrice = ($slot['sellingPrice'] ?? '') !== '' ? (float) $slot['sellingPrice'] : null;

            $pairs[] = [
                'unit' => (string) $slot['unit'],
                'per' => (float) $slot['per'],
                'cost' => $cost,
                'sellingPrice' => $sellingPrice,
            ];
        }

        return $pairs;
    }
}
