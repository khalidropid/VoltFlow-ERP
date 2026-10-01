<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelAdjustment extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid','station_id','fuel_type_id','fuel_tank_id','adjusted_at',
        'quantity_delta','reason','status',
    ];

    protected function casts(): array
    {
        return [
            'adjusted_at' => 'datetime',
            'quantity_delta' => 'decimal:4',
        ];
    }

    public function fuelType(): BelongsTo { return $this->belongsTo(FuelType::class); }
    public function fuelTank(): BelongsTo { return $this->belongsTo(FuelTank::class); }
}
