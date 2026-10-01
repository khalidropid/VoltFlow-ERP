<?php

namespace App\Services\Integration;

use App\Models\CollectorLocation;
use App\Models\IntegrationBatch;
use App\Models\IntegrationEvent;
use App\Models\IntegrationSource;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\DB;

class IntegrationService
{
    public function recordEvent(
        IntegrationSource $source,
        string $eventUuid,
        string $entityType,
        string $externalId,
        string $eventType,
        array $payload,
        ?IntegrationBatch $batch = null,
    ): array {
        if (! $source->is_active) {
            throw new IntegrationException('Integration source is inactive.', 422);
        }

        if ($batch && $batch->integration_source_id !== $source->id) {
            throw new IntegrationException('Integration batch belongs to a different integration source.', 422);
        }

        return DB::transaction(function () use ($source, $eventUuid, $entityType, $externalId, $eventType, $payload, $batch): array {
            $existing = IntegrationEvent::query()
                ->where('event_uuid', $eventUuid)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $same = $existing->integration_source_id === $source->id
                    && $existing->entity_type === $entityType
                    && $existing->external_id === $externalId
                    && $existing->event_type === $eventType
                    && $this->canonicalPayload($existing->payload) === $this->canonicalPayload($payload);

                if (! $same) {
                    throw new IntegrationException(
                        'Event UUID was already used for a different integration event.',
                        409
                    );
                }

                return [$existing, false];
            }

            $existing = IntegrationEvent::query()
                ->where('integration_source_id', $source->id)
                ->where('entity_type', $entityType)
                ->where('external_id', $externalId)
                ->where('event_type', $eventType)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($this->canonicalPayload($existing->payload) !== $this->canonicalPayload($payload)) {
                    throw new IntegrationException(
                        'External integration identifier was already used with a different payload.',
                        409
                    );
                }

                return [$existing, false];
            }

            $event = IntegrationEvent::create([
                'integration_source_id' => $source->id,
                'integration_batch_id' => $batch?->id,
                'event_uuid' => $eventUuid,
                'entity_type' => $entityType,
                'external_id' => $externalId,
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => 'received',
                'received_at' => now(),
            ]);

            if ($batch) {
                IntegrationBatch::query()
                    ->whereKey($batch->id)
                    ->increment('received_count');
            }

            return [$event, true];
        }, 3);
    }

    public function registerDevice(User $user, string $deviceId, ?string $platform, ?string $appVersion): array
    {
        return DB::transaction(function () use ($user, $deviceId, $platform, $appVersion): array {
            $device = UserDevice::query()->firstOrCreate(
                ['user_id' => $user->id, 'device_id' => $deviceId],
                [
                    'platform' => $platform,
                    'app_version' => $appVersion,
                    'is_approved' => false,
                    'last_seen_at' => now(),
                ]
            );

            $created = $device->wasRecentlyCreated;

            if (! $created) {
                $device->update([
                    'platform' => $platform,
                    'app_version' => $appVersion,
                    'last_seen_at' => now(),
                ]);
            }

            return [$device->fresh(), $created];
        }, 3);
    }

    public function approveDevice(UserDevice $device): UserDevice
    {
        $device->update([
            'is_approved' => true,
            'approved_at' => now(),
            'last_seen_at' => $device->last_seen_at ?? now(),
        ]);

        return $device->fresh();
    }

    public function recordCollectorLocation(
        User $collector,
        int $stationId,
        string $deviceId,
        string $latitude,
        string $longitude,
        string $recordedAt,
        ?string $accuracyMeters = null,
    ): CollectorLocation {
        if (! $collector->stations()->whereKey($stationId)->exists()) {
            throw new IntegrationException('Collector is not assigned to this station.', 403);
        }

        $device = UserDevice::query()
            ->where('user_id', $collector->id)
            ->where('device_id', $deviceId)
            ->first();

        if (! $device || ! $device->is_approved) {
            throw new IntegrationException('The collector device is not approved.', 403);
        }

        $device->update(['last_seen_at' => now()]);

        return CollectorLocation::create([
            'station_id' => $stationId,
            'collector_id' => $collector->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'recorded_at' => $recordedAt,
            'accuracy_meters' => $accuracyMeters,
            'device_id' => $deviceId,
        ]);
    }

    private function canonicalPayload(?array $payload): string
    {
        $payload ??= [];

        return json_encode($this->sortKeys($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function sortKeys(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeys($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
