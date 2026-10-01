<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'code',
        'name',
        'is_paid',
        'annual_days',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'annual_days' => 'decimal:4',
        ];
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
