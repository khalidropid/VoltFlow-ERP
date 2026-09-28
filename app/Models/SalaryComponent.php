<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'code',
        'name',
        'type',
        'is_taxable',
    ];

    protected function casts(): array
    {
        return ['is_taxable' => 'boolean'];
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function payrollSlipLines(): HasMany
    {
        return $this->hasMany(PayrollSlipLine::class);
    }
}
