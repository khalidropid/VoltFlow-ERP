<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelReceipt extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid','station_id','fuel_type_id','fuel_tank_id','received_at',
        'quantity','unit_cost','total_cost','supplier_reference','notes','status',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    public function fuelType(): BelongsTo { return $this->belongsTo(FuelType::class); }
    public function fuelTank(): BelongsTo { return $this->belongsTo(FuelTank::class); }
}
