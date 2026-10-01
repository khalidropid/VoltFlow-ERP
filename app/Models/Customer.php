<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'code',
        'name',
        'phone',
        'address',
        'status',
        'opening_balance',
    ];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:4'];
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function accountLink(): HasOne
    {
        return $this->hasOne(CustomerAccountLink::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(CustomerConnection::class);
    }
}
