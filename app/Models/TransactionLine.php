<?php

namespace App\Models;

use App\Enums\RecordType;
use App\Support\Packs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line on a transaction.
 *
 * base_qty is the quantity in the item family's base unit and unit_price is
 * the price for one of the line's own unit. Both are computed once on write,
 * which is what lets stock and reports aggregate without joining units.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $item_id
 * @property float $qty
 * @property string $unit_code
 * @property float $base_qty
 * @property float $unit_price
 * @property float $total
 * @property array<int, array{unit: string, per: float}>|null $inner_units
 * @property float|null $cost
 * @property float|null $selling_price
 */
class TransactionLine extends Model
{
    protected $fillable = [
        'transaction_id', 'item_id', 'qty', 'unit_code', 'base_qty', 'unit_price', 'total',
        'inner_units', 'unit_cost', 'cost', 'selling_price',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'base_qty' => 'float',
            'unit_price' => 'float',
            'total' => 'float',
            'inner_units' => 'array',
            'unit_cost' => 'float',
            'cost' => 'float',
            'selling_price' => 'float',
        ];
    }

    /**
     * What this line made, or null when its cost was never recorded.
     *
     * Null propagates rather than becoming zero. A line from before cost was
     * snapshotted has an unknown profit, and reporting it as zero would quietly
     * drag a day's total toward looking break-even when it is really incomplete.
     */
    public function profit(): ?float
    {
        return $this->cost === null ? null : $this->total - $this->cost;
    }

    public function getProfitAttribute(): ?float
    {
        return $this->profit();
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_code', 'code');
    }

    public function recordType(): ?RecordType
    {
        return $this->transaction?->type;
    }

    /**
     * What this receipt declared one trading unit was made of, if anything.
     *
     * Only a purchase carries this: it is the declaration, and later sales read
     * the item's most recent one rather than repeating it.
     */
    public function packs(): Packs
    {
        return Packs::fromArray($this->inner_units);
    }

    public function unitCost(): ?float
    {
        return $this->unit_cost;
    }
}
