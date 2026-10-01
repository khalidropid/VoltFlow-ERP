<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DebitNote extends Model
{
    use HasStation;
    protected $fillable=['transaction_uuid','station_id','customer_id','invoice_id','number','note_date','amount','reason','status','journal_entry_id'];
    protected function casts(): array { return ['note_date'=>'date','amount'=>'decimal:4']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function items(): HasMany { return $this->hasMany(DebitNoteItem::class); }
}
