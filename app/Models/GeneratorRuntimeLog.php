<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratorRuntimeLog extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'generator_id',
        'started_at',
        'stopped_at',
        'hours',
        'load_percent',
        'energy_kwh',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'hours' => 'decimal:4',
            'load_percent' => 'decimal:4',
            'energy_kwh' => 'decimal:4',
        ];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(Generator::class);
    }
}
