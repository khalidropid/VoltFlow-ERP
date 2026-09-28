<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationReading extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid',
        'station_id',
        'generator_id',
        'reading_at',
        'energy_kwh',
        'active_power_kw',
        'reactive_power_kvar',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reading_at' => 'datetime',
            'energy_kwh' => 'decimal:4',
            'active_power_kw' => 'decimal:4',
            'reactive_power_kvar' => 'decimal:4',
        ];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(Generator::class);
    }
}
