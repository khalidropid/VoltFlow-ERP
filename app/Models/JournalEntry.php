<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = [
        'station_id', 'fiscal_period_id', 'number', 'entry_date', 'description',
        'status', 'source_type', 'source_id', 'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function lines() { return $this->hasMany(JournalEntryLine::class); }
}
