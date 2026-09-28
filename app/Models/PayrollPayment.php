<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayment extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid',
        'station_id',
        'payroll_slip_id',
        'cash_account_id',
        'paid_at',
        'amount',
        'method',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'amount' => 'decimal:4',
        ];
    }

    public function payrollSlip(): BelongsTo
    {
        return $this->belongsTo(PayrollSlip::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }
}
