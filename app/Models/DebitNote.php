<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;

class DebitNote extends Model
{
    use HasStation;
    protected $fillable=['transaction_uuid','station_id','customer_id','invoice_id','number','note_date','amount','reason','status'];
    protected function casts(): array{return ['note_date'=>'date','amount'=>'decimal:4'];}
    public function items(){return $this->hasMany(DebitNoteItem::class);}
    public function invoice(){return $this->belongsTo(Invoice::class);}
}

public function journalEntry() { return $this->belongsTo(\App\Models\JournalEntry::class); }
