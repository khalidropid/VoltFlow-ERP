<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Integration\IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function register(Request $request, IntegrationService $service): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:150'],
            'platform' => ['nullable', 'string', 'max:30'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        [$device, $created] = $service->registerDevice(
            user: $request->user(),
            deviceId: $data['device_id'],
            platform: $data['platform'] ?? null,
            appVersion: $data['app_version'] ?? null,
        );

        return response()->json([
            'data' => $device,
            'message' => $created ? 'Device registration submitted for approval.' : 'Device registration refreshed.',
        ], $created ? 201 : 200);
    }
}
