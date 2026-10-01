<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlan extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id','asset_id','name','interval_days','interval_hours','next_due_on','is_active',
    ];

    protected function casts(): array
    {
        return [
            'interval_days' => 'integer',
            'interval_hours' => 'decimal:4',
            'next_due_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
}
