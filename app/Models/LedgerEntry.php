<?php

namespace App\Models;

use App\Enums\RecordType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One side of the ledger: a sale or a purchase, with its lines.
 *
 * Sales and purchases live in their own tables, but the code that reads the
 * ledger treats them the same way -- a dated header that owns lines and can be
 * totalled. That shared shape lives here so the two concrete models stay thin,
 * and so a report can hold a mixed collection of both without caring which is
 * which beyond the type it reports.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $branch_id
 * @property Carbon $transacted_on
 * @property int|null $user_id
 * @property-read Collection<int, LedgerLine> $lines
 * @property float $total
 * @property float|null $cost
 * @property float|null $profit
 */
abstract class LedgerEntry extends Model
{
    protected $appends = ['total', 'cost', 'profit'];

    protected function casts(): array
    {
        return [
            'transacted_on' => 'date',
        ];
    }

    /** Which side of the ledger this header records. */
    abstract public function type(): RecordType;

    /** @return HasMany<LedgerLine, $this> */
    abstract public function lines(): HasMany;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function total(): float
    {
        return (float) $this->lines->sum('total');
    }

    public function getTotalAttribute(): float
    {
        return $this->total();
    }

    public function getCostAttribute(): ?float
    {
        return $this->lines->sum('cost');
    }

    public function getProfitAttribute(): ?float
    {
        $cost = $this->cost;

        return $cost === null ? null : $this->total - $cost;
    }

    public function isSale(): bool
    {
        return $this->type() === RecordType::Sale;
    }

    public function isPurchase(): bool
    {
        return $this->type() === RecordType::Purchase;
    }
}
