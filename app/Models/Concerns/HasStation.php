<?php

namespace App\Models\Concerns;

use App\Models\Station;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasStation
{
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function scopeForStation(Builder $query, int $stationId): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('station_id'), $stationId);
    }
}
