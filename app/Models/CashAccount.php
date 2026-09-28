<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAccount extends Model
{
    protected $fillable = ['station_id','code','name','type','account_id','balance','is_active'];

    protected function casts(): array { return ['is_active' => 'boolean', 'balance' => 'decimal:4']; }

    public function station() { return $this->belongsTo(Station::class); }
}
