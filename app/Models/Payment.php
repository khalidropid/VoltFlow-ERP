<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['transaction_uuid','station_id','customer_id','invoice_id','collector_id','cash_account_id','receipt_number','paid_at','amount','method','status','notes','journal_entry_id'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'decimal:4'];
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function collector() { return $this->belongsTo(User::class, 'collector_id'); }
    public function cashAccount() { return $this->belongsTo(CashAccount::class); }
}
