<?php

namespace App\Models;

use App\Enums\UnitFamily;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Something being traded.
 *
 * name_key is the lowercased name and carries the unique index, so "Rice" and
 * "rice" resolve to the same item on every database driver regardless of
 * collation.
 *
 * @property int $id
 * @property string $name
 * @property string $name_key
 * @property UnitFamily $family
 * @property string $default_unit_code
 * @property string|null $pack_size
 * @property string|null $notes
 * @property bool $is_active
 * @property-read Unit|null $displayUnit
 */
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'name_key', 'family', 'default_unit_code', 'pack_size', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'family' => UnitFamily::class,
            'is_active' => 'boolean',
        ];
    }

    /** The unit this item is normally traded in -- a bag, a crate, a dozen. */
    public function displayUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'default_unit_code', 'code');
    }

    /** Alias for displayUnit for API compatibility. */
    public function unit(): BelongsTo
    {
        return $this->displayUnit();
    }

    /** Get the family label for the item. */
    public function getFamilyLabelAttribute(): string
    {
        return $this->family?->label();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    public function packs(): HasMany
    {
        return $this->hasMany(TransactionLine::class)
            ->whereNotNull('inner_units')
            ->distinct('inner_units');
    }

    public function transactions(): HasManyThrough
    {
        // item_id joins the lines to this item, and the line's own id joins the
        // transaction through it. Both are named because the defaults are a guess:
        // left to itself Laravel reaches for a transaction_line_id that has never
        // existed, and the relation only fails when something finally calls it.
        return $this->hasManyThrough(
            Transaction::class,
            TransactionLine::class,
            'item_id',
            'id',
            'id',
            'id',
        );
    }

    /**
     * Has this item ever been traded? If not, its family and trading unit are
     * safe to edit; once it has lines, they are frozen.
     */
    public function hasHistory(): bool
    {
        return $this->lines()->exists();
    }

    /** Collapse whitespace so " Rice 50kg " and "Rice 50kg" are one item. */
    public static function normaliseName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }

    public static function nameKey(string $name): string
    {
        return mb_strtolower(self::normaliseName($name));
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeAlphabetical(Builder $query): void
    {
        $query->orderBy('name');
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.mb_strtolower($term).'%';

        $query->where(fn (Builder $q) => $q->where('name_key', 'like', $like)->orWhere('pack_size', 'like', $like));
    }
}
