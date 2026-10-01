<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    protected $table = 'units_of_measure';
    protected $fillable = ['code', 'name', 'symbol'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
