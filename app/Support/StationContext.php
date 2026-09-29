<?php

namespace App\Support;

use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class StationContext
{
    public const SESSION_KEY = 'voltflow.current_station_id';

    public function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function stations(): Collection
    {
        $user = $this->user();

        return $user ? $user->stations()->orderBy('name')->get() : collect();
    }

    public function currentId(): ?int
    {
        $user = $this->user();

        if (! $user) {
            return null;
        }

        $allowed = $user->stations()->pluck('stations.id');

        if ($allowed->isEmpty()) {
            return null;
        }

        $sessionId = session(self::SESSION_KEY);

        if ($sessionId !== null && $allowed->contains((int) $sessionId)) {
            return (int) $sessionId;
        }

        $defaultId = $user->stations()
            ->wherePivot('is_default', true)
            ->value('stations.id');

        return $defaultId !== null ? (int) $defaultId : (int) $allowed->first();
    }

    public function current(): ?Station
    {
        $id = $this->currentId();

        return $id ? Station::query()->find($id) : null;
    }

    public function set(int $stationId): Station
    {
        $user = $this->user();

        abort_unless($user, 403);

        $station = $user->stations()->whereKey($stationId)->first();

        abort_unless($station, 403, 'You do not have access to this station.');

        session([self::SESSION_KEY => $station->getKey()]);

        return $station;
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
