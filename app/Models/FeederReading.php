<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeederReading extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid',
        'station_id',
        'feeder_id',
        'reading_at',
        'energy_kwh',
        'current_amp',
        'voltage',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reading_at' => 'datetime',
            'energy_kwh' => 'decimal:4',
            'current_amp' => 'decimal:4',
            'voltage' => 'decimal:4',
        ];
    }

    public function feeder(): BelongsTo
    {
        return $this->belongsTo(Feeder::class);
    }
}
