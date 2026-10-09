<?php

namespace App\Models;

use App\Enums\UnitFamily;
use App\Support\ValueFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $label
 * @property string $symbol
 * @property string|null $plural
 * @property UnitFamily $family
 * @property float $base_multiplier
 * @property bool $is_fraction
 * @property bool $is_decimal
 * @property bool $is_default
 * @property bool $is_active
 */
class Unit extends Model
{
    protected $fillable = [
        'code', 'label', 'symbol', 'plural', 'family',
        'base_multiplier', 'is_fraction', 'is_decimal', 'is_default', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'family' => UnitFamily::class,
            'base_multiplier' => 'float',
            'is_fraction' => 'boolean',
            'is_decimal' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** Items that are normally traded in this unit. */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'default_unit_code', 'code');
    }

    /** Transaction lines recorded in this unit. */
    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class, 'unit_code', 'code');
    }

    /**
     * Has this unit ever been used? If so its family and multiplier are frozen,
     * because changing either would silently rewrite the meaning of history.
     */
    public function isInUse(): bool
    {
        return $this->lines()->exists() || $this->items()->exists();
    }

    /** The phrase shown after a quantity, pluralised where it matters. */
    public function quantityLabel(int|float|string $quantity): string
    {
        $quantity = (float) $quantity;
        $word = abs($quantity) == 1 ? $this->label : ($this->plural ?: $this->label);

        return ValueFormatter::plain($quantity, 3).' '.$word;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeInFamily(Builder $query, UnitFamily $family): void
    {
        $query->where('family', $family->value);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('label');
    }
}
