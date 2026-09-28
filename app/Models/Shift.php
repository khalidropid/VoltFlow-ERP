<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'code',
        'name',
        'starts_at',
        'ends_at',
        'break_minutes',
    ];

    protected function casts(): array
    {
        return ['break_minutes' => 'integer'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }
}
