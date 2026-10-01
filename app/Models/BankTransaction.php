<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankTransaction extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid',
        'station_id',
        'cash_account_id',
        'transaction_date',
        'bank_reference',
        'description',
        'debit',
        'credit',
        'balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'debit' => 'decimal:4',
            'credit' => 'decimal:4',
            'balance' => 'decimal:4',
        ];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function reconciliationItems(): HasMany
    {
        return $this->hasMany(BankReconciliationItem::class);
    }
}
