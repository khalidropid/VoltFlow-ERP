<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRecord extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id', 'employee_id', 'payroll_slip_id', 'work_date', 'hours',
        'rate_multiplier', 'amount', 'status', 'approved_by', 'notes',
    ];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'hours' => 'decimal:4', 'rate_multiplier' => 'decimal:4', 'amount' => 'decimal:4'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function payrollSlip(): BelongsTo { return $this->belongsTo(PayrollSlip::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
