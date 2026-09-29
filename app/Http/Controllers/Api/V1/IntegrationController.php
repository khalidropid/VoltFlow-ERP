<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntegrationBatch;
use App\Models\IntegrationSource;
use App\Services\Integration\IntegrationException;
use App\Services\Integration\IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function storeEvent(Request $request, IntegrationService $service): JsonResponse
    {
        abort_unless($request->user()->can('integration.manage'), 403);

        $data = $request->validate([
            'source_code' => ['required', 'string', 'max:50'],
            'batch_uuid' => ['nullable', 'uuid'],
            'event_uuid' => ['required', 'uuid'],
            'entity_type' => ['required', 'string', 'max:100'],
            'external_id' => ['required', 'string', 'max:150'],
            'event_type' => ['required', 'string', 'max:80'],
            'payload' => ['required', 'array'],
        ]);

        $source = IntegrationSource::query()->where('code', $data['source_code'])->first();

        if (! $source) {
            throw new IntegrationException('Integration source was not found.', 404);
        }

        $batch = null;
        if (! empty($data['batch_uuid'])) {
            $batch = IntegrationBatch::query()->where('batch_uuid', $data['batch_uuid'])->first();
            if (! $batch) {
                throw new IntegrationException('Integration batch was not found.', 404);
            }
        }

        [$event, $created] = $service->recordEvent(
            source: $source,
            eventUuid: $data['event_uuid'],
            entityType: $data['entity_type'],
            externalId: $data['external_id'],
            eventType: $data['event_type'],
            payload: $data['payload'],
            batch: $batch,
        );

        return response()->json(['data' => $event], $created ? 201 : 200);
    }
}
