<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feeder extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'code',
        'name',
        'capacity_kw',
        'status',
    ];

    protected function casts(): array
    {
        return ['capacity_kw' => 'decimal:4'];
    }

    public function readings(): HasMany
    {
        return $this->hasMany(FeederReading::class);
    }

    public function customerConnections(): HasMany
    {
        return $this->hasMany(CustomerConnection::class);
    }
}
