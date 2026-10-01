<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meter extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'customer_id',
        'serial_number',
        'meter_number',
        'meter_type',
        'phase',
        'multiplier',
        'initial_reading',
        'installed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
            'initial_reading' => 'decimal:4',
            'installed_at' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(CustomerConnection::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(MeterInstallation::class);
    }
}
