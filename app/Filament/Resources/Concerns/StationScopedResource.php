<?php

namespace App\Filament\Resources\Concerns;

use App\Support\StationContext;
use Illuminate\Database\Eloquent\Builder;

trait StationScopedResource
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $stationId = app(StationContext::class)->currentId();

        return $stationId
            ? $query->where($query->getModel()->getTable() . '.station_id', $stationId)
            : $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(static::$viewPermission) ?? false;
    }

    public static function canCreate(): bool
    {
        return (auth()->user()?->can(static::$managePermission) ?? false)
            && app(StationContext::class)->currentId() !== null;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can(static::$managePermission) ?? false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
