<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'employee_id',
        'attendance_date',
        'check_in',
        'check_out',
        'worked_hours',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'worked_hours' => 'decimal:4',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class);
    }
}
