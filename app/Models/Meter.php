<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meter extends Model
{
    protected $fillable = ['station_id','customer_id','serial_number','meter_type','multiplier','initial_reading','installed_at','status'];

    protected function casts(): array
    {
        return ['multiplier' => 'decimal:4', 'initial_reading' => 'decimal:4', 'installed_at' => 'date'];
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function readings() { return $this->hasMany(MeterReading::class); }
}
