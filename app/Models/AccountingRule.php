<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingRule extends Model
{
    protected $fillable = [
        'station_id',
        'transaction_type',
        'debit_account_id',
        'credit_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    public function scopeForStationOrGlobal(Builder $query, int $stationId): Builder
    {
        return $query->where(function (Builder $q) use ($stationId) {
            $q->where('station_id', $stationId)->orWhereNull('station_id');
        });
    }
}
