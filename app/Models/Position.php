<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasStation;

    protected $fillable = ['station_id', 'code', 'name'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
