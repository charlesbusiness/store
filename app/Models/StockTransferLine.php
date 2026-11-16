<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferLine extends Model
{
    protected $fillable = ['stock_transfer_id', 'item_id', 'qty', 'unit_code', 'base_qty', 'unit_cost', 'total_cost'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'base_qty' => 'float', 'unit_cost' => 'float', 'total_cost' => 'float'];
    }

    public function transfer(): BelongsTo { return $this->belongsTo(StockTransfer::class, 'stock_transfer_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class, 'unit_code', 'code'); }
}
