<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollSlip extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'slip_number', 'gross_amount',
        'deduction_amount', 'net_amount', 'status', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:4',
            'deduction_amount' => 'decimal:4',
            'net_amount' => 'decimal:4',
        ];
    }

    public function payrollRun(): BelongsTo { return $this->belongsTo(PayrollRun::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function lines(): HasMany { return $this->hasMany(PayrollSlipLine::class); }
    public function advanceRepayments(): HasMany { return $this->hasMany(AdvanceRepayment::class); }
    public function payment(): HasOne { return $this->hasOne(PayrollPayment::class); }
}
