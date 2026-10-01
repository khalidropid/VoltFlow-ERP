<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Generator extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'code',
        'name',
        'manufacturer',
        'model',
        'serial_number',
        'capacity_kw',
        'rated_voltage',
        'fuel_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'capacity_kw' => 'decimal:4',
            'rated_voltage' => 'decimal:4',
        ];
    }

    public function runtimeLogs(): HasMany
    {
        return $this->hasMany(GeneratorRuntimeLog::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(GenerationReading::class);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
