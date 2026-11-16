<?php

namespace App\Models;

use App\Enums\RecordType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchase: stock bought in, optionally the display record of an internal
 * stock transfer.
 *
 * @property int|null $stock_transfer_id
 */
class Purchase extends LedgerEntry
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'branch_id', 'transacted_on', 'user_id', 'stock_transfer_id'];

    public function type(): RecordType
    {
        return RecordType::Purchase;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function isInternalTransfer(): bool
    {
        return $this->stock_transfer_id !== null;
    }
}
