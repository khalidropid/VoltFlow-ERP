<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceAdjustment extends Model
{
    protected $fillable=['invoice_id','type','reason','amount','created_by'];
    protected function casts(): array{return ['amount'=>'decimal:4'];}
    public function invoice(){return $this->belongsTo(Invoice::class);}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
}