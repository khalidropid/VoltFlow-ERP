<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Integration\IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectorLocationController extends Controller
{
    public function store(Request $request, IntegrationService $service): JsonResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'integer'],
            'device_id' => ['required', 'string', 'max:150'],
            'latitude' => ['required', 'regex:/^-?\d{1,3}(?:\.\d{1,7})?$/'],
            'longitude' => ['required', 'regex:/^-?\d{1,3}(?:\.\d{1,7})?$/'],
            'recorded_at' => ['required', 'date'],
            'accuracy_meters' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'],
        ]);

        abort_unless(
            (float) $data['latitude'] >= -90 && (float) $data['latitude'] <= 90
            && (float) $data['longitude'] >= -180 && (float) $data['longitude'] <= 180,
            422,
            'Invalid geographic coordinates.'
        );

        $location = $service->recordCollectorLocation(
            collector: $request->user(),
            stationId: (int) $data['station_id'],
            deviceId: $data['device_id'],
            latitude: $data['latitude'],
            longitude: $data['longitude'],
            recordedAt: $data['recorded_at'],
            accuracyMeters: $data['accuracy_meters'] ?? null,
        );

        return response()->json(['data' => $location], 201);
    }
}
