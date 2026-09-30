<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebitNoteItem extends Model
{
    protected $fillable=['debit_note_id','description','quantity','unit_rate','amount','line_no'];
    protected function casts(): array{return ['quantity'=>'decimal:4','unit_rate'=>'decimal:4','amount'=>'decimal:4'];}
    public function debitNote(){return $this->belongsTo(DebitNote::class);}
}