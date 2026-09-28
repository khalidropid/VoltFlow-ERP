<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = ['invoice_id','quantity','unit_rate','amount','description','line_no'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_rate' => 'decimal:4', 'amount' => 'decimal:4'];
    }
}
