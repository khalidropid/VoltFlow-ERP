<?php

namespace Tests\Feature\Security;

use App\Filament\Resources\CustomerResource;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Support\StationContext;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuditMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_edit_is_rejected_across_station_contexts(): void
    {
        $this->seed(AccessControlSeeder::class);

        $stationA = Station::create([
            'code' => 'ST-SCOPE-A',
            'name' => 'Scope A',
            'name_ar' => 'النطاق أ',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);

        $stationB = Station::create([
            'code' => 'ST-SCOPE-B',
            'name' => 'Scope B',
            'name_ar' => 'النطاق ب',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('station_manager');
        $user->stations()->attach($stationA->id, ['is_default' => true]);
        $user->stations()->attach($stationB->id, ['is_default' => false]);

        $customerA = Customer::create([
            'station_id' => $stationA->id,
            'code' => 'CUS-A',
            'name' => 'Customer A',
            'status' => 'active',
            'opening_balance' => '0.0000',
        ]);
        $customerB = Customer::create([
            'station_id' => $stationB->id,
            'code' => 'CUS-B',
            'name' => 'Customer B',
            'status' => 'active',
            'opening_balance' => '0.0000',
        ]);

        $this->actingAs($user);
        app(StationContext::class)->set($stationA->id);

        $this->assertTrue(CustomerResource::canEdit($customerA));
        $this->assertFalse(CustomerResource::canEdit($customerB));
    }

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
