<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationEvent extends Model
{
    protected $fillable = [
        'integration_source_id', 'integration_batch_id', 'event_uuid',
        'entity_type', 'external_id', 'event_type', 'payload',
        'status', 'error_message', 'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(IntegrationBatch::class, 'integration_batch_id');
    }
}
