<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationBatch extends Model
{
    protected $fillable = [
        'integration_source_id', 'batch_uuid', 'external_batch_id',
        'started_at', 'completed_at', 'status',
        'received_count', 'processed_count', 'failed_count', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(IntegrationEvent::class);
    }
}
