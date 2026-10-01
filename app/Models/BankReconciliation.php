<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'cash_account_id',
        'statement_date',
        'statement_balance',
        'book_balance',
        'difference',
        'status',
        'prepared_by',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'statement_balance' => 'decimal:4',
            'book_balance' => 'decimal:4',
            'difference' => 'decimal:4',
        ];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BankReconciliationItem::class);
    }
}
