<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalPeriod extends Model
{
    protected $fillable = ['station_id', 'name', 'starts_on', 'ends_on', 'status'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }
}
