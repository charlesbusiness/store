<?php

namespace App\Http\Requests;

use App\Enums\UnitFamily;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the unit form.
 *
 * A unit that has been used to record a quantity keeps its family and
 * multiplier forever: the stock of every item measured in it was converted
 * through that multiplier when it was written.
 */
class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * True when the unit has been used to record a quantity, so its code,
     * family and multiplier may no longer be changed.
     */
    private function isFrozen(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit && $unit->isInUse();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');
        $frozen = $this->isFrozen();

        return [
            // The form disables these inputs when frozen, so they arrive
            // absent: require them only while editable, reject them after.
            'code' => [
                Rule::requiredIf(! $frozen),
                'string',
                'max:32',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('units', 'code')->ignore($unit instanceof Unit ? $unit->id : null),
                Rule::prohibitedIf($frozen),
            ],
            'label' => ['required', 'string', 'max:64'],
            'symbol' => ['nullable', 'string', 'max:32'],
            'plural' => ['nullable', 'string', 'max:64'],
            'family' => [
                Rule::requiredIf(! $frozen),
                Rule::enum(UnitFamily::class),
                Rule::prohibitedIf($frozen),
            ],
            'base_multiplier' => [
                Rule::requiredIf(! $frozen),
                'numeric',
                'gt:0',
                'max:1000000000',
                Rule::prohibitedIf($frozen),
            ],
            'is_decimal' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use lowercase letters, numbers and underscores only.',
            'base_multiplier.gt' => 'The multiplier must be greater than zero.',
            'family.prohibited' => 'This unit is in use, so its measurement family is fixed.',
            'base_multiplier.prohibited' => 'This unit is in use, so its multiplier is fixed.',
            'code.prohibited' => 'This unit is in use, so its code is fixed.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'base_multiplier' => 'multiplier',
            'default_unit_code' => 'trading unit',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = [
            'label' => $this->string('label')->toString(),
            'symbol' => $this->input('symbol') ?: '',
            'plural' => $this->input('plural') ?: null,
            'is_decimal' => $this->boolean('is_decimal'),
            'sort' => (int) ($this->input('sort') ?: 0),
            'is_active' => $this->boolean('is_active'),
        ];

        if (! $this->isFrozen()) {
            $data['code'] = mb_strtolower($this->string('code')->toString());
            $data['family'] = $this->string('family')->toString();
            $data['base_multiplier'] = (float) $this->input('base_multiplier');
            $data['is_fraction'] = $this->boolean('is_fraction');
        }

        return $data;
    }
}
