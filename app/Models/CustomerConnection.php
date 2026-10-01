<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerConnection extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'customer_id',
        'meter_id',
        'feeder_id',
        'connected_on',
        'disconnected_on',
        'connection_status',
        'connection_load_kw',
    ];

    protected function casts(): array
    {
        return [
            'connected_on' => 'date',
            'disconnected_on' => 'date',
            'connection_load_kw' => 'decimal:4',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function feeder(): BelongsTo
    {
        return $this->belongsTo(Feeder::class);
    }
}
