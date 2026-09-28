<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAccountLink extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'employee_id',
        'payable_account_id',
        'salary_expense_account_id',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payable_account_id');
    }

    public function salaryExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'salary_expense_account_id');
    }
}
