<?php

namespace App\Http\Requests\Api;

use App\Models\Item;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'name' => [
                'required',
                'string',
                'max:160',
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId): void {
                    if (Item::query()->where('tenant_id', $tenantId)->where('name_key', Item::nameKey((string) $value))->exists()) {
                        $fail('An item with this name already exists.');
                    }
                },
            ],
            'family' => ['required', Rule::in(\App\Enums\UnitFamily::values())],
            'default_unit_code' => ['required', 'string', 'exists:units,code'],
        ];
    }
}
