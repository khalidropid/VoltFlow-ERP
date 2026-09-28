<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TariffSlab extends Model
{
    protected $fillable = ['tariff_id','from_unit','to_unit','rate','sort_order'];

    protected function casts(): array
    {
        return ['from_unit' => 'decimal:4', 'to_unit' => 'decimal:4', 'rate' => 'decimal:4'];
    }
}
