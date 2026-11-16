<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = ['tenant_id', 'source_branch_id', 'destination_branch_id', 'user_id', 'transferred_on', 'status', 'notes'];

    protected function casts(): array
    {
        return ['transferred_on' => 'date'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function sourceBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'source_branch_id'); }
    public function destinationBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'destination_branch_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function lines(): HasMany { return $this->hasMany(StockTransferLine::class); }

    public function purchase(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Transaction::class, 'stock_transfer_id');
    }
}
