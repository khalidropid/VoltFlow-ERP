<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'code',
        'starts_on',
        'ends_on',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }
}
