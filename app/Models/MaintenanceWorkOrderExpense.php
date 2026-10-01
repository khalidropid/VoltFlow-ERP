<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWorkOrderExpense extends Model
{
    protected $fillable = ['maintenance_work_order_id','description','amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWorkOrder::class, 'maintenance_work_order_id');
    }
}
