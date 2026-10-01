<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelIssue extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid','station_id','fuel_type_id','fuel_tank_id','generator_id',
        'issued_at','quantity','unit_cost','total_cost','status','notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    public function fuelType(): BelongsTo { return $this->belongsTo(FuelType::class); }
    public function fuelTank(): BelongsTo { return $this->belongsTo(FuelTank::class); }
    public function generator(): BelongsTo { return $this->belongsTo(Generator::class); }
}
