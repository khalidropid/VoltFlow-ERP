<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAdvance extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid',
        'station_id',
        'employee_id',
        'advance_date',
        'amount',
        'balance',
        'status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'advance_date' => 'date',
            'amount' => 'decimal:4',
            'balance' => 'decimal:4',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(AdvanceRepayment::class);
    }
}
