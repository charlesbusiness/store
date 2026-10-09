<?php

namespace App\Rules;

use App\Models\Item;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An item's name must be unique, compared the way the app stores it.
 *
 * Rule::unique() would compare the submitted text against the name_key column,
 * so "  RICE " would sail past a stored "rice" and then trip the database
 * constraint as a 500. The key is derived here instead.
 */
class UniqueItemName implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = Item::nameKey((string) $value);

        if ($key === '') {
            return;
        }

        $exists = Item::query()
            ->where('name_key', $key)
            ->when($this->ignoreId, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('An item with that name already exists.');
        }
    }
}
