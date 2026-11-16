<?php

namespace App\Models;

use App\Enums\ReportingMode;
use App\Enums\StockManagementMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'stock_management_mode', 'reporting_mode'];

    protected function casts(): array
    {
        return [
            'stock_management_mode' => StockManagementMode::class,
            'reporting_mode' => ReportingMode::class,
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['role', 'is_active'])->withTimestamps();
    }
}
