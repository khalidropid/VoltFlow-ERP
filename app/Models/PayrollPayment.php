<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayment extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid', 'station_id', 'payroll_slip_id', 'cash_account_id',
        'journal_entry_id', 'reversal_journal_entry_id', 'paid_at', 'amount',
        'method', 'status', 'voided_at', 'voided_by',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'decimal:4', 'voided_at' => 'datetime'];
    }

    public function payrollSlip(): BelongsTo { return $this->belongsTo(PayrollSlip::class); }
    public function cashAccount(): BelongsTo { return $this->belongsTo(CashAccount::class); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function reversalJournalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id'); }
    public function voidedBy(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
}
