<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\FuelTankResource;
use App\Filament\Resources\GeneratorResource;
use App\Filament\Resources\ItemResource;
use App\Filament\Resources\WarehouseResource;
use App\Models\Asset;
use App\Models\Station;
use App\Models\User;
use App\Models\UnitOfMeasure;
use App\Models\Item;
use App\Models\Warehouse;
use App\Support\StationContext;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6OperationalResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_resources_are_station_scoped_and_role_authorized(): void
    {
        $this->seed(AccessControlSeeder::class);

        $stationA = Station::create([
            'code' => 'ST-A',
            'name' => 'Station A',
            'name_ar' => 'المحطة أ',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);

        $stationB = Station::create([
            'code' => 'ST-B',
            'name' => 'Station B',
            'name_ar' => 'المحطة ب',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);

        GeneratorResource::getModel()::create([
            'station_id' => $stationA->id,
            'code' => 'GEN-A',
            'name' => 'Generator A',
            'capacity_kw' => '100.0000',
            'status' => 'active',
        ]);

        GeneratorResource::getModel()::create([
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

        WarehouseResource::getModel()::create([
            'station_id' => $stationA->id,
            'code' => 'WH-A',
            'name' => 'Warehouse A',
            'type' => 'main',
            'is_active' => true,
        ]);

        AssetResource::getModel()::create([
            'station_id' => $stationA->id,
            'code' => 'AST-A',
            'name' => 'Asset A',
            'purchase_cost' => '1000.0000',
            'residual_value' => '100.0000',
            'status' => 'active',
        ]);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('station_manager');
        $user->stations()->attach($stationA->id, ['is_default' => true]);
        $user->stations()->attach($stationB->id, ['is_default' => false]);

        $this->actingAs($user);

        $context = app(StationContext::class);

        $this->assertTrue($user->can('generation.manage'));
        $this->assertTrue($user->can('fuel.manage'));
        $this->assertTrue($user->can('inventory.manage'));
        $this->assertTrue($user->can('maintenance.manage'));

        $this->assertSame(['GEN-A'], GeneratorResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['TANK-A'], FuelTankResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['ITEM-A'], ItemResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['WH-A'], WarehouseResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['AST-A'], AssetResource::getEloquentQuery()->pluck('code')->all());

        $context->set($stationB->id);

        $this->assertSame(['GEN-B'], GeneratorResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['TANK-B'], FuelTankResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['ITEM-B'], ItemResource::getEloquentQuery()->pluck('code')->all());
    }
}
