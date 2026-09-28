<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = [
        'station_id', 'fiscal_period_id', 'number', 'entry_date', 'description',
        'status', 'source_type', 'source_id', 'created_by', 'posted_by', 'posted_at', 'reversal_of_journal_entry_id',
    ];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function lines() { return $this->hasMany(JournalEntryLine::class); }
    public function reversalOf() { return $this->belongsTo(JournalEntry::class, 'reversal_of_journal_entry_id'); }
    public function reversals() { return $this->hasMany(JournalEntry::class, 'reversal_of_journal_entry_id'); }
}
