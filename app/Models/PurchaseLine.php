<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a purchase.
 *
 * The foreign key is named explicitly: the relation method is `transaction()`
 * to match the shared line contract, and left to itself Eloquent would reach
 * for a transaction_id that no longer exists.
 */
class PurchaseLine extends LedgerLine
{
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }
}
