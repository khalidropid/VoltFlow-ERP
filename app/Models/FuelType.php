<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelType extends Model
{
    protected $fillable = ['station_id', 'code', 'name', 'unit', 'density', 'is_active'];

    protected function casts(): array
    {
        return ['density' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function tanks(): HasMany { return $this->hasMany(FuelTank::class); }
    public function receipts(): HasMany { return $this->hasMany(FuelReceipt::class); }
    public function issues(): HasMany { return $this->hasMany(FuelIssue::class); }
    public function adjustments(): HasMany { return $this->hasMany(FuelAdjustment::class); }
    public function stockMovements(): HasMany { return $this->hasMany(FuelStockMovement::class); }
}
