<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelStockMovement extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid','station_id','fuel_type_id','fuel_tank_id','movement_type',
        'quantity','unit_cost','reference_type','reference_id','moved_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'moved_at' => 'datetime',
        ];
    }

    public function fuelType(): BelongsTo { return $this->belongsTo(FuelType::class); }
    public function fuelTank(): BelongsTo { return $this->belongsTo(FuelTank::class); }
}
