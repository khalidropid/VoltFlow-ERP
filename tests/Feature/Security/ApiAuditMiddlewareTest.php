<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuditMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_station_mutation_is_audited(): void
    {
        $this->seed(AccessControlSeeder::class);

        $station = Station::create([
            'code' => 'ST-AUDIT',
            'name' => 'Audit Station',
            'name_ar' => 'محطة التدقيق',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('collector');
        $user->stations()->attach($station->id, ['is_default' => true]);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/devices/register', [
            'station_id' => $station->id,
            'device_id' => 'AUDIT-DEVICE-01',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ], [
            'Idempotency-Key' => 'audit-device-01',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'api.request',
            'user_id' => $user->id,
            'station_id' => $station->id,
        ]);
    }
}
