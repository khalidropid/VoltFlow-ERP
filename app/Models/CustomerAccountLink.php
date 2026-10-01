<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAccountLink extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'customer_id',
        'receivable_account_id',
        'revenue_account_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'receivable_account_id');
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'revenue_account_id');
    }
}
