<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWorkOrderLabor extends Model
{
    protected $fillable = ['maintenance_work_order_id','employee_id','hours','rate','amount'];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:4',
            'rate' => 'decimal:4',
            'amount' => 'decimal:4',
        ];
    }

    public function workOrder(): BelongsTo { return $this->belongsTo(MaintenanceWorkOrder::class, 'maintenance_work_order_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
