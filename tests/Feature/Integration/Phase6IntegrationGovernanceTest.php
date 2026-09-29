<?php

namespace Tests\Feature\Integration;

use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\CollectorLocationResource;
use App\Filament\Resources\IntegrationEventResource;
use App\Filament\Resources\UserDeviceResource;
use App\Models\AuditLog;
use App\Models\IntegrationEvent;
use App\Models\IntegrationSource;
use App\Models\Station;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Integration\IntegrationService;
use App\Support\StationContext;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6IntegrationGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_ingress_device_approval_and_location_are_controlled(): void
    {
        $this->seed(AccessControlSeeder::class);

        $source = IntegrationSource::create([
            'code' => 'flutter-mobile',
            'name' => 'Flutter Mobile Collectors',
            'type' => 'flutter',
            'is_active' => true,
        ]);

        $stationA = Station::create([
            'code' => 'ST-IA',
            'name' => 'Station A',
            'name_ar' => 'المحطة أ',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);

        $stationB = Station::create([
            'code' => 'ST-IB',
            'name' => 'Station B',
            'name_ar' => 'المحطة ب',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $admin->stations()->attach($stationA->id, ['is_default' => true]);

        $collector = User::factory()->create(['is_active' => true]);
        $collector->assignRole('collector');
        $collector->stations()->attach($stationA->id, ['is_default' => true]);

        $this->actingAs($collector, 'sanctum');

        $this->postJson('/api/v1/integration/events', [
            'source_code' => 'flutter-mobile',
            'event_uuid' => (string) Str::uuid(),
            'entity_type' => 'collection',
            'external_id' => 'COL-DENIED',
            'event_type' => 'received',
            'payload' => ['amount' => '50.00'],
        ], [
            'Idempotency-Key' => 'evt-denied-1001',
        ])->assertForbidden();

        $this->actingAs($admin, 'sanctum');

        $eventPayload = [
            'source_code' => 'flutter-mobile',
            'event_uuid' => (string) Str::uuid(),
            'entity_type' => 'collection',
            'external_id' => 'COL-1001',
            'event_type' => 'received',
            'payload' => ['amount' => '100.00', 'customer_id' => 5],
        ];

        $this->postJson('/api/v1/integration/events', $eventPayload, [
            'Idempotency-Key' => 'evt-key-1001',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'received');

        $this->postJson('/api/v1/integration/events', $eventPayload, [
            'Idempotency-Key' => 'evt-key-1002',
        ])->assertOk();

        $sameExternalIdNewUuid = $eventPayload;
        $sameExternalIdNewUuid['event_uuid'] = (string) Str::uuid();

        $this->postJson('/api/v1/integration/events', $sameExternalIdNewUuid, [
            'Idempotency-Key' => 'evt-key-1003',
        ])->assertOk();

        $changedPayload = $eventPayload;
        $changedPayload['event_uuid'] = (string) Str::uuid();
        $changedPayload['payload']['amount'] = '200.00';

        $this->postJson('/api/v1/integration/events', $changedPayload, [
            'Idempotency-Key' => 'evt-key-1004',
        ])->assertStatus(409);

        $this->assertSame(1, IntegrationEvent::query()->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'integration.event.received')->count());

        $this->actingAs($collector, 'sanctum');

        $this->postJson('/api/v1/devices/register', [
            'device_id' => 'PHONE-A',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ], [
            'Idempotency-Key' => 'device-key-1001',
        ])->assertCreated()
            ->assertJsonPath('data.is_approved', false);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $collector->id,
            'device_id' => 'PHONE-A',
            'is_approved' => false,
        ]);

        $this->assertSame(1, AuditLog::query()->where('event', 'device.registered')->count());

        $this->postJson('/api/v1/collector-locations', [
            'station_id' => $stationA->id,
            'device_id' => 'PHONE-A',
            'latitude' => '15.3694450',
            'longitude' => '44.1910060',
            'recorded_at' => now()->toDateTimeString(),
        ], [
            'Idempotency-Key' => 'location-key-1001',
        ])->assertForbidden();

        $this->actingAs($admin, 'sanctum');

        $device = UserDevice::query()
            ->where('user_id', $collector->id)
            ->where('device_id', 'PHONE-A')
            ->firstOrFail();

        app(IntegrationService::class)->approveDevice($device);

        $this->actingAs($collector, 'sanctum');

        $this->postJson('/api/v1/collector-locations', [
            'station_id' => $stationA->id,
            'device_id' => 'PHONE-A',
            'latitude' => '15.3694450',
            'longitude' => '44.1910060',
            'recorded_at' => now()->toDateTimeString(),
            'accuracy_meters' => '12.50',
        ], [
            'Idempotency-Key' => 'location-key-1002',
        ])->assertCreated();

        $this->assertSame(1, AuditLog::query()->where('event', 'collector.location.recorded')->count());

        $this->postJson('/api/v1/collector-locations', [
            'station_id' => $stationB->id,
            'device_id' => 'PHONE-A',
            'latitude' => '15.3694450',
            'longitude' => '44.1910060',
            'recorded_at' => now()->toDateTimeString(),
        ], [
            'Idempotency-Key' => 'location-key-1003',
        ])->assertForbidden();

        $this->actingAs($admin, 'sanctum');
        app(StationContext::class)->set($stationA->id);

        $this->assertTrue($admin->can('integration.view'));
        $this->assertTrue($admin->can('integration.manage'));
        $this->assertTrue(AuditLogResource::canViewAny());
        $this->assertTrue(UserDeviceResource::canViewAny());
        $this->assertTrue(IntegrationEventResource::canViewAny());
        $this->assertSame(
            ['PHONE-A'],
            UserDeviceResource::getEloquentQuery()->pluck('device_id')->all()
        );

        app(StationContext::class)->set($stationB->id);

        $this->assertSame([], UserDeviceResource::getEloquentQuery()->pluck('device_id')->all());
        $this->assertSame([], CollectorLocationResource::getEloquentQuery()->pluck('id')->all());
    }
}
