<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceRepayment extends Model
{
    protected $fillable = [
        'employee_advance_id',
        'payroll_slip_id',
        'repayment_date',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'repayment_date' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function employeeAdvance(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdvance::class);
    }

    public function payrollSlip(): BelongsTo
    {
        return $this->belongsTo(PayrollSlip::class);
    }
}
