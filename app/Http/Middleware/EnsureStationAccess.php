<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\StationContext;

class EnsureStationAccess
{
    public function handle(Request $request, Closure $next)
    {
        $stationId = $request->route('station') ?? $request->input('station_id');

        if (!$stationId || !$request->user()->stations()->whereKey($stationId)->exists()) {
            return response()->json(['message' => 'You are not authorized for this station.'], 403);
        }

        $token = $request->user()->currentAccessToken();
        if ($token && !$token->can('station:' . $stationId)) {
            return response()->json(['message' => 'The current token is not authorized for this station.'], 403);
        }

        app(StationContext::class)->set((int) $stationId);

        return $next($request);
    }
}
