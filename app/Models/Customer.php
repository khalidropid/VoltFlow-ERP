<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasStation;

    protected $fillable = ['station_id','code','name','phone','address','status','opening_balance'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:4'];
    }

    public function meters() { return $this->hasMany(Meter::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function payments() { return $this->hasMany(Payment::class); }
}
