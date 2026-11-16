<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The optional customer named on a sale.
 *
 * @property int $id
 * @property int $sale_id
 * @property string $name
 */
class CustomerName extends Model
{
    protected $fillable = ['tenant_id', 'sale_id', 'name'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
