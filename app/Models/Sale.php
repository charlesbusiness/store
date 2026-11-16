<?php

namespace App\Models;

use App\Enums\RecordType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A sale: stock drawn from the shelf, priced per unit, optionally named to a
 * customer and marked paid.
 *
 * @property bool $paid
 * @property-read CustomerName|null $customerName
 */
class Sale extends LedgerEntry
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'branch_id', 'transacted_on', 'user_id', 'paid'];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'paid' => 'boolean',
        ];
    }

    public function type(): RecordType
    {
        return RecordType::Sale;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function customerName(): HasOne
    {
        return $this->hasOne(CustomerName::class);
    }
}
