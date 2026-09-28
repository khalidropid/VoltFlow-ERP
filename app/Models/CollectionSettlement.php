<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionSettlement extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid',
        'station_id',
        'collector_account_id',
        'cash_account_id',
        'created_by',
        'number',
        'settled_at',
        'amount',
        'status',
        'journal_entry_id',
        'reversal_journal_entry_id',
        'voided_by',
        'voided_at',
        'void_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'settled_at' => 'datetime',
            'voided_at' => 'datetime',
            'amount' => 'decimal:4',
        ];
    }

    public function collectorAccount(): BelongsTo
    {
        return $this->belongsTo(CollectorAccount::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
