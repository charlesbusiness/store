<?php

namespace App\Http\Requests;

use App\Enums\UnitFamily;
use App\Models\Item;
use App\Models\Unit;
use App\Rules\UniqueItemName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the item form.
 *
 * The family is not a field: it is derived from the trading unit, which is the
 * only thing that can give it meaning.
 *
 * An item that has never traded may be corrected freely. Once it has lines,
 * its family and trading unit are frozen, because changing either would
 * silently restate what its history means.
 */
class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * True when the item has lines, so its family and trading unit may no
     * longer be changed.
     */
    private function isFrozen(): bool
    {
        $item = $this->route('item');

        return $item instanceof Item && $item->hasHistory();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $frozen = $this->isFrozen();

        return [
            'name' => [
                'required',
                'string',
                'max:160',
                // Compared on the derived key, so "  RICE " cannot slip past a
                // stored "rice" and reach the unique index as a 500.
                new UniqueItemName($item instanceof Item ? $item->id : null),
            ],
            // The family is not asked for: it is read off the unit chosen below,
            // so the two can never contradict each other. Required while
            // editable, and rejected outright once frozen, when the form disables
            // the input and it arrives absent.
            'default_unit_code' => [
                Rule::requiredIf(! $frozen),
                'string',
                // Only active units, matching what the dropdown offers.
                Rule::exists('units', 'code')->where('is_active', true),
                Rule::prohibitedIf($frozen),
            ],
            'pack_size' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'default_unit_code.exists' => 'Choose a unit this item is traded in.',
            'default_unit_code.required' => 'Choose a unit this item is traded in.',
        ];

        if ($this->isFrozen()) {
            $messages['default_unit_code.prohibited'] = 'This item has trading history, so its trading unit is fixed.';
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'default_unit_code' => 'trading unit',
            'pack_size' => 'pack size',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $item = $this->route('item');
        $editing = $item instanceof Item;

        $data = [
            'name' => Item::normaliseName($this->string('name')->toString()),
            'pack_size' => $this->input('pack_size') ?: null,
            'notes' => $this->input('notes') ?: null,
            'is_active' => $this->boolean('is_active'),
        ];

        // Frozen once the item has history, so only send them on create.
        if (! $editing) {
            $code = $this->string('default_unit_code')->toString();
            $unit = Unit::where('code', $code)->first();

            // Guarded by exists() above, so this is only a type narrowing.
            $data['name_key'] = Item::nameKey($this->string('name')->toString());
            $data['family'] = $unit?->family ?? UnitFamily::Count;
            $data['default_unit_code'] = $code;
        }

        return $data;
    }
}
