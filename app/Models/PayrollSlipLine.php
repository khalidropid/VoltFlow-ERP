<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSlipLine extends Model
{
    protected $fillable = [
        'payroll_slip_id',
        'salary_component_id',
        'description',
        'line_type',
        'amount',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }

    public function payrollSlip(): BelongsTo
    {
        return $this->belongsTo(PayrollSlip::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }
}
