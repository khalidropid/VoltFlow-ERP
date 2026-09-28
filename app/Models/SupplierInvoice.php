<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SupplierInvoice extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid', 'station_id', 'supplier_id', 'goods_receipt_id', 'number',
        'invoice_date', 'due_date', 'subtotal', 'tax', 'total', 'paid_amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:4',
            'tax' => 'decimal:4',
            'total' => 'decimal:4',
            'paid_amount' => 'decimal:4',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(
            SupplierPayment::class,
            SupplierPaymentAllocation::class,
            'supplier_invoice_id',
            'id',
            'id',
            'supplier_payment_id'
        );
    }
}
