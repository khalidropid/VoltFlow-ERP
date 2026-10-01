<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashClosing extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'cash_account_id',
        'closing_date',
        'system_balance',
        'counted_balance',
        'variance',
        'closed_by',
        'closed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'system_balance' => 'decimal:4',
            'counted_balance' => 'decimal:4',
            'variance' => 'decimal:4',
            'closed_at' => 'datetime',
        ];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
