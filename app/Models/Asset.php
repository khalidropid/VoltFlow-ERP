<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasStation;

    protected $fillable = [
        'station_id','asset_category_id','code','name','serial_number','acquired_on',
        'purchase_cost','residual_value','useful_life_months','depreciation_start_on','status',
    ];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'purchase_cost' => 'decimal:4',
            'residual_value' => 'decimal:4',
            'useful_life_months' => 'integer',
            'depreciation_start_on' => 'date',
        ];
    }

    public function category(): BelongsTo { return $this->belongsTo(AssetCategory::class, 'asset_category_id'); }
    public function maintenancePlans(): HasMany { return $this->hasMany(MaintenancePlan::class); }
    public function workOrders(): HasMany { return $this->hasMany(MaintenanceWorkOrder::class); }
}
