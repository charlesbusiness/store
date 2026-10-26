<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The optional customer named on a sale.
 *
 * @property int $id
 * @property int $transaction_id
 * @property string $name
 */
class CustomerName extends Model
{
    protected $fillable = ['transaction_id', 'name'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
