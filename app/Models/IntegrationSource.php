<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationSource extends Model
{
    protected $fillable = ['code', 'name', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(IntegrationBatch::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(IntegrationEvent::class);
    }

    public function legacyMappings(): HasMany
    {
        return $this->hasMany(LegacyIdMapping::class);
    }
}
