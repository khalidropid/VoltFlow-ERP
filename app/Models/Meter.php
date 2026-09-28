<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;

class Meter extends Model
{
    use HasStation;

    protected $fillable = ['station_id','customer_id','serial_number','meter_type','multiplier','initial_reading','installed_at','status'];

    protected function casts(): array
    {
        return ['multiplier' => 'decimal:4','initial_reading' => 'decimal:4','installed_at' => 'date'];
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function readings() { return $this->hasMany(MeterReading::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
}
