<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAdjustment extends Model
{
    protected $fillable = ['invoice_id','type','reason','amount','created_by','journal_entry_id'];
    protected function casts(): array { return ['amount'=>'decimal:4']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
}
