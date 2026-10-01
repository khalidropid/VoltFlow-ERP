<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id',
        'code',
        'name',
        'type',
        'inventory_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_account_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
