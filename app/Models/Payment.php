<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid',
        'station_id',
        'customer_id',
        'invoice_id',
        'collector_id',
        'cash_account_id',
        'receipt_number',
        'paid_at',
        'amount',
        'method',
        'status',
        'notes',
        'journal_entry_id',
        'reversal_journal_entry_id',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'amount' => 'decimal:4',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
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
