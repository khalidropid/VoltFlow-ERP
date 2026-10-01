<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterInstallation extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id', 'meter_id', 'customer_id', 'installed_on',
        'removed_on', 'initial_reading', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'installed_on' => 'date',
            'removed_on' => 'date',
            'initial_reading' => 'decimal:4',
        ];
    }

    public function meter(): BelongsTo { return $this->belongsTo(Meter::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}