<?php

namespace App\Http\Requests\Api;

use App\Models\Item;
use App\Support\TenantContext;
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
        $tenantId = app(TenantContext::class)->tenantId();
        $itemId = $this->route('item')?->id ?? $this->route('item');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:160',
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId, $itemId): void {
                    $exists = Item::query()
                        ->where('tenant_id', $tenantId)
                        ->where('name_key', Item::nameKey((string) $value))
                        ->when($itemId !== null, fn ($query) => $query->where('id', '!=', $itemId))
                        ->exists();

                    if ($exists) {
                        $fail('An item with this name already exists.');
                    }
                },
            ],
            'family' => ['sometimes', 'required', Rule::in(\App\Enums\UnitFamily::values())],
            'default_unit_code' => ['sometimes', 'required', 'string', 'exists:units,code'],
        ];
    }
}
