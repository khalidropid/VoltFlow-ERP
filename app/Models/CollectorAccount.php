<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectorAccount extends Model
{
    protected $fillable = ['station_id','collector_id','cash_account_id','account_id','opening_balance','balance','status'];

    protected function casts(): array { return ['opening_balance' => 'decimal:4', 'balance' => 'decimal:4']; }

    public function collector() { return $this->belongsTo(User::class, 'collector_id'); }
    public function account() { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
}
