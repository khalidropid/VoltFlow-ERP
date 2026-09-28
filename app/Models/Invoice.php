<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasStation;

    protected $fillable = ['transaction_uuid','station_id','customer_id','meter_id','reading_id','tariff_id','billing_cycle_id','number','invoice_date','due_date','previous_reading','current_reading','consumption','subtotal','discount','tax','total','paid_amount','status','journal_entry_id'];

    protected function casts(): array
    {
        return [
            'invoice_date'=>'date','due_date'=>'date','previous_reading'=>'decimal:4','current_reading'=>'decimal:4',
            'consumption'=>'decimal:4','subtotal'=>'decimal:4','discount'=>'decimal:4','tax'=>'decimal:4','total'=>'decimal:4','paid_amount'=>'decimal:4'
        ];
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function meter() { return $this->belongsTo(Meter::class); }
    public function reading() { return $this->belongsTo(MeterReading::class,'reading_id'); }
    public function tariff() { return $this->belongsTo(Tariff::class); }
    public function billingCycle() { return $this->belongsTo(BillingCycle::class); }
    public function items() { return $this->hasMany(InvoiceItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }
}
