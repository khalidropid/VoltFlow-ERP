<?php

namespace App\Models;

use App\Models\Concerns\HasStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceWorkOrder extends Model
{
    use HasStation;

    protected $fillable = [
        'transaction_uuid','station_id','asset_id','number','title','type','priority',
        'status','opened_on','completed_on','problem_description','resolution',
    ];

    protected function casts(): array
    {
        return [
            'opened_on' => 'date',
            'completed_on' => 'date',
        ];
    }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function parts(): HasMany { return $this->hasMany(MaintenanceWorkOrderPart::class); }
    public function labor(): HasMany { return $this->hasMany(MaintenanceWorkOrderLabor::class); }
    public function expenses(): HasMany { return $this->hasMany(MaintenanceWorkOrderExpense::class); }
}
