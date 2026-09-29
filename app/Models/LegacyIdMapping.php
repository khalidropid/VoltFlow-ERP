<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyIdMapping extends Model
{
    protected $fillable = [
        'integration_source_id', 'legacy_table', 'legacy_id',
        'entity_type', 'entity_id',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }
}
