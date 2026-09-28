<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelTank extends Model
{
    use HasStation;

    protected $fillable = ['station_id', 'code', 'name', 'capacity', 'current_quantity', 'is_active'];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:4',
            'current_quantity' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function receipts(): HasMany { return $this->hasMany(FuelReceipt::class); }
    public function issues(): HasMany { return $this->hasMany(FuelIssue::class); }
    public function adjustments(): HasMany { return $this->hasMany(FuelAdjustment::class); }
    public function stockMovements(): HasMany { return $this->hasMany(FuelStockMovement::class); }
}
