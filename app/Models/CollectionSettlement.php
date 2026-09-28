<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionSettlement extends Model
{
    protected $fillable = ['transaction_uuid','station_id','collector_account_id','cash_account_id','created_by','number','settled_at','amount','status','notes'];

    protected function casts(): array
    {
        return ['settled_at' => 'datetime', 'amount' => 'decimal:4'];
    }
}
