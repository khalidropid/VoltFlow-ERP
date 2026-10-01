<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'user_id',
        'department_id',
        'position_id',
        'employee_no',
        'name',
        'phone',
        'email',
        'joined_on',
        'left_on',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function overtimeRecords(): HasMany
    {
        return $this->hasMany(OvertimeRecord::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class);
    }

    public function salaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function payrollSlips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }

    public function accountLink(): HasOne
    {
        return $this->hasOne(EmployeeAccountLink::class);
    }
}
