<?php

namespace App\Models;

use App\Enums\RecordType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One sale or one purchase, with its lines.
 *
 * @property int $id
 * @property RecordType $type
 * @property Carbon $transacted_on
 * @property int|null $user_id
 * @property-read CustomerName|null $customerName
 * @property-read Collection<int, TransactionLine> $lines
 * @property float $total
 * @property float|null $cost
 * @property float|null $profit
 */
class Transaction extends Model
{
    protected $fillable = ['type', 'transacted_on', 'user_id', 'paid'];

    protected $appends = ['total', 'cost', 'profit'];

    protected function casts(): array
    {
        return [
            'type' => RecordType::class,
            'transacted_on' => 'date',
            'paid' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    public function customerName(): HasOne
    {
        return $this->hasOne(CustomerName::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return $this->type === RecordType::Sale;
    }

    public function isPurchase(): bool
    {
        return $this->type === RecordType::Purchase;
    }

    public function scopePurchases($query)
    {
        return $query->where('type', RecordType::Purchase);
    }

    public function scopeSales($query)
    {
        return $query->where('type', RecordType::Sale);
    }
}
