<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'payroll_period_id',
        'run_at',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['run_at' => 'datetime'];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function slips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }
}
