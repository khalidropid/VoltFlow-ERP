<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAdjustment extends Model
{
    protected $fillable = [
        'attendance_log_id',
        'approved_by',
        'hours_delta',
        'reason',
    ];

    protected function casts(): array
    {
        return ['hours_delta' => 'decimal:4'];
    }

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
