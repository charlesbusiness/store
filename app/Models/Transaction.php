<?php

namespace App\Models;

use App\Enums\RecordType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One sale or one purchase, with its lines.
 *
 * @property int $id
 * @property RecordType $type
 * @property Carbon $transacted_on
 * @property int|null $user_id
 * @property-read Collection<int, TransactionLine> $lines
 */
class Transaction extends Model
{
    protected $fillable = ['type', 'transacted_on', 'user_id', 'paid'];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function total(): float
    {
        return (float) $this->lines->sum('total');
    }

    public function isSale(): bool
    {
        return $this->type === RecordType::Sale;
    }
}
