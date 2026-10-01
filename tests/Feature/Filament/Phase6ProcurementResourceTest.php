<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\PurchaseRequestResource;
use App\Filament\Resources\SupplierResource;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Station;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\StationContext;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6ProcurementResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_resources_are_station_scoped_and_role_authorized(): void
    {
        $this->seed(AccessControlSeeder::class);

        $stationA = Station::create([
            'code'=>'ST-A','name'=>'Station A','name_ar'=>'المحطة أ',
            'timezone'=>'Asia/Aden','currency_code'=>'YER','is_active'=>true,
        ]);
        $stationB = Station::create([
            'code'=>'ST-B','name'=>'Station B','name_ar'=>'المحطة ب',
            'timezone'=>'Asia/Aden','currency_code'=>'YER','is_active'=>true,
        ]);

        $uom = UnitOfMeasure::create(['code'=>'PCS','name'=>'Pieces','symbol'=>'pcs']);

        $itemA = Item::create([
            'station_id'=>$stationA->id,'code'=>'ITEM-A','name'=>'Item A',
            'unit_of_measure_id'=>$uom->id,'item_type'=>'stock',
            'standard_cost'=>'10.0000','reorder_level'=>'2.0000','is_active'=>true,
        ]);
        $itemB = Item::create([
            'station_id'=>$stationB->id,'code'=>'ITEM-B','name'=>'Item B',
            'unit_of_measure_id'=>$uom->id,'item_type'=>'stock',
            'standard_cost'=>'20.0000','reorder_level'=>'2.0000','is_active'=>true,
        ]);

        $supplierA = Supplier::create([
            'station_id'=>$stationA->id,'code'=>'SUP-A','name'=>'Supplier A','status'=>'active',
        ]);
        Supplier::create([
            'station_id'=>$stationB->id,'code'=>'SUP-B','name'=>'Supplier B','status'=>'active',
        ]);

        $requestA = PurchaseRequest::create([
            'transaction_uuid'=>(string)\Illuminate\Support\Str::uuid(),
            'station_id'=>$stationA->id,'requested_by'=>null,'number'=>'PR-A',
            'request_date'=>now()->toDateString(),'status'=>'draft',
        ]);
        $requestA->items()->create([
            'item_id'=>$itemA->id,'quantity'=>'5.0000','description'=>'Initial stock','line_no'=>1,
        ]);

        $orderA = PurchaseOrder::create([
            'transaction_uuid'=>(string)\Illuminate\Support\Str::uuid(),
            'station_id'=>$stationA->id,'supplier_id'=>$supplierA->id,
            'purchase_request_id'=>$requestA->id,'number'=>'PO-A',
            'order_date'=>now()->toDateString(),'status'=>'draft','total'=>'50.0000',
        ]);
        $orderA->items()->create([
            'item_id'=>$itemA->id,'quantity'=>'5.0000','unit_cost'=>'10.0000',
            'amount'=>'50.0000','line_no'=>1,
        ]);

        $user = User::factory()->create(['is_active'=>true]);
        $user->assignRole('storekeeper');
        $user->stations()->attach($stationA->id,['is_default'=>true]);
        $user->stations()->attach($stationB->id,['is_default'=>false]);

        $this->actingAs($user);

        $this->assertTrue($user->can('procurement.view'));
        $this->assertTrue($user->can('procurement.manage'));

        $this->assertSame(['SUP-A'], SupplierResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame(['PR-A'], PurchaseRequestResource::getEloquentQuery()->pluck('number')->all());
        $this->assertSame(['PO-A'], PurchaseOrderResource::getEloquentQuery()->pluck('number')->all());

        app(StationContext::class)->set($stationB->id);

        $this->assertSame(['SUP-B'], SupplierResource::getEloquentQuery()->pluck('code')->all());
        $this->assertSame([], PurchaseRequestResource::getEloquentQuery()->pluck('number')->all());
        $this->assertSame([], PurchaseOrderResource::getEloquentQuery()->pluck('number')->all());
    }
}
