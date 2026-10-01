<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\FuelTankResource;
use App\Filament\Resources\GeneratorResource;
use App\Filament\Resources\ItemResource;
use App\Filament\Resources\WarehouseResource;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Generator;
use App\Models\Station;
use App\Models\User;
use App\Models\UnitOfMeasure;
use App\Models\Item;
use App\Models\Warehouse;
use App\Support\StationContext;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6ManagementOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_resources_are_station_scoped_and_role_authorized(): void
    {
        $this->seed(AccessControlSeeder::class);

        $stationA = $this->station('ST-A', 'Station A');
        $stationB = $this->station('ST-B', 'Station B');

        Customer::create([
            'station_id' => $stationA->id,
            'code' => 'CUST-A',
            'name' => 'Customer A',
            'status' => 'active',
            'opening_balance' => '0.0000',
        ]);

        Customer::create([
            'station_id' => $stationB->id,
            'code' => 'CUST-B',
            'name' => 'Customer B',
            'status' => 'active',
            'opening_balance' => '0.0000',
        ]);

        Generator::create([
            'station_id' => $stationA->id,
            'code' => 'GEN-A',
            'name' => 'Generator A',
            'capacity_kw' => '100.0000',
            'status' => 'active',
        ]);

        Generator::create([
            'station_id' => $stationB->id,
            'code' => 'GEN-B',
            'name' => 'Generator B',
            'capacity_kw' => '200.0000',
            'status' => 'active',
        ]);

        FuelTankResource::getModel()::create([
            'station_id' => $stationA->id,
            'code' => 'TANK-A',
            'name' => 'Tank A',
            'capacity' => '1000.0000',
            'current_quantity' => '250.5000',
            'is_active' => true,
        ]);

        FuelTankResource::getModel()::create([
            'station_id' => $stationB->id,
            'code' => 'TANK-B',
            'name' => 'Tank B',
            'capacity' => '2000.0000',
            'current_quantity' => '500.5000',
            'is_active' => true,
        ]);

        $uom = UnitOfMeasure::create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'symbol' => 'pcs',
        ]);

        Item::create([
            'station_id' => $stationA->id,
            'code' => 'ITEM-A',
            'name' => 'Item A',
            'unit_of_measure_id' => $uom->id,
            'item_type' => 'stock',
            'standard_cost' => '10.1250',
            'reorder_level' => '5.0000',
            'is_active' => true,
        ]);

        Item::create([
            'station_id' => $stationB->id,
            'code' => 'ITEM-B',
            'name' => 'Item B',
            'unit_of_measure_id' => $uom->id,
            'item_type' => 'stock',
            'standard_cost' => '20.1250',
            'reorder_level' => '5.0000',
            'is_active' => true,
        ]);

        Warehouse::create([
            'station_id' => $stationA->id,
            'code' => 'WH-A',
            'name' => 'Warehouse A',
            'type' => 'main',
            'is_active' => true,
        ]);

        Asset::create([
            'station_id' => $stationA->id,
            'code' => 'AST-A',
            'name' => 'Asset A',
            'purchase_cost' => '1000.0000',
            'residual_value' => '100.0000',
            'status' => 'active',
        ]);

        /** @var User $user */
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('station_manager');
        $user->stations()->attach($stationA->id, ['is_default' => true]);
        $user->stations()->attach($stationB->id, ['is_default' => false]);

        $this->actingAs($user);

        $context = app(StationContext::class);

        $this->assertTrue($user->can('customers.manage'));
        $this->assertTrue($user->can('generation.manage'));
        $this->assertTrue($user->can('fuel.manage'));
        $this->assertTrue($user->can('inventory.manage'));
        $this->assertTrue($user->can('maintenance.manage'));

        $this->assertTrue(CustomerResource::canCreate());
        $this->assertTrue(GeneratorResource::canCreate());
        $this->assertTrue(FuelTankResource::canCreate());
        $this->assertTrue(ItemResource::canCreate());
        $this->assertTrue(WarehouseResource::canCreate());
        $this->assertTrue(AssetResource::canCreate());

        $this->assertSame(['CUST-A'], CustomerResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['GEN-A'], GeneratorResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['TANK-A'], FuelTankResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['ITEM-A'], ItemResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['WH-A'], WarehouseResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['AST-A'], AssetResource::getEloquentQuery()->pluck('code')->all());

        $context->set($stationB->id);

        $this->assertSame(['CUST-B'], CustomerResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['GEN-B'], GeneratorResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['TANK-B'], FuelTankResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['ITEM-B'], ItemResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame([], WarehouseResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame([], AssetResource::getEloquentQuery()->pluck('code')->all());
    }

    public function test_management_resources_do_not_allow_delete_operations(): void
    {
        $this->seed(AccessControlSeeder::class);

        $station = $this->station('ST-A', 'Station A');

        /** @var User $user */
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('station_manager');
        $user->stations()->attach($station->id, ['is_default' => true]);

        $this->actingAs($user);

        $this->assertFalse(CustomerResource::canDelete(new Customer(['station_id' => $station->id])));
        $this->assertFalse(GeneratorResource::canDelete(new Generator(['station_id' => $station->id])));
        $this->assertFalse(AssetResource::canDelete(new Asset(['station_id' => $station->id])));
    }

    private function station(string $code, string $name): Station
    {
        return Station::create([
            'code' => $code,
            'name' => $name,
            'name_ar' => $name,
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);
    }
}
