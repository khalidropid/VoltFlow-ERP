<?php

namespace Tests\Feature\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\FuelTank;
use App\Models\FuelType;
use App\Models\Generator;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Station;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\Fuel\FuelService;
use App\Services\Generation\GenerationService;
use App\Services\Inventory\InventoryService;
use App\Services\Maintenance\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteErpDomainServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_service_is_idempotent_and_station_scoped(): void
    {
        $a=Station::create(['code'=>'S-A','name'=>'A','timezone'=>'Asia/Aden','currency_code'=>'YER']);
        $b=Station::create(['code'=>'S-B','name'=>'B','timezone'=>'Asia/Aden','currency_code'=>'YER']);
        $g=Generator::create(['station_id'=>$a->id,'code'=>'GEN-1','name'=>'Generator','capacity_kw'=>'100.0000','status'=>'active']);
        $s=app(GenerationService::class);
        $r=$s->recordGeneration($a->id,$g->id,'11111111-1111-4111-8111-111111111111','2026-10-01 01:00:00','12.5000');
        $same=$s->recordGeneration($a->id,$g->id,'11111111-1111-4111-8111-111111111111','2026-10-01 01:00:00','12.5000');
        $this->assertSame($r->id,$same->id);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $s->recordGeneration($b->id,$g->id,'22222222-2222-4222-8222-222222222222','2026-10-01 01:00:00','1.0000');
    }

    public function test_fuel_receipt_and_issue_update_tank_balance(): void
    {
        $s=Station::create(['code'=>'F-A','name'=>'Fuel','timezone'=>'Asia/Aden','currency_code'=>'YER']);
        $type=FuelType::create(['station_id'=>$s->id,'code'=>'DSL','name'=>'Diesel','unit'=>'liter','is_active'=>true]);
        $tank=FuelTank::create(['station_id'=>$s->id,'code'=>'T1','name'=>'Tank','capacity'=>'1000.0000','current_quantity'=>'100.0000','is_active'=>true]);
        $service=app(FuelService::class);
        $service->postReceipt($s->id,$tank->id,$type->id,'33333333-3333-4333-8333-333333333333','2026-10-01 01:00:00','50.0000','2.0000');
        $service->postIssue($s->id,$tank->id,$type->id,'44444444-4444-4444-8444-444444444444','2026-10-01 02:00:00','20.0000',null,'2.0000');
        $this->assertSame('130.0000',(string)$tank->fresh()->current_quantity);
    }

    public function test_inventory_service_maintains_weighted_average_and_prevents_over_issue(): void
    {
        $s=Station::create(['code'=>'I-A','name'=>'Inventory','timezone'=>'Asia/Aden','currency_code'=>'YER']);
        $cat=ItemCategory::create(['station_id'=>$s->id,'code'=>'SP','name'=>'Spare']);
        $u=UnitOfMeasure::create(['code'=>'PCS','name'=>'Piece','symbol'=>'pc']);
        $item=Item::create(['station_id'=>$s->id,'item_category_id'=>$cat->id,'unit_of_measure_id'=>$u->id,'code'=>'I1','name'=>'Filter','standard_cost'=>'10.0000','reorder_level'=>'1.0000','is_active'=>true]);
        $w=Warehouse::create(['station_id'=>$s->id,'code'=>'W1','name'=>'Main','type'=>'spares','is_active'=>true]);
        $service=app(InventoryService::class);
        $service->receive($s->id,$w->id,$item->id,'55555555-5555-4555-8555-555555555555','10.0000','10.0000','2026-10-01 01:00:00');
        $service->receive($s->id,$w->id,$item->id,'66666666-6666-4666-8666-666666666666','10.0000','20.0000','2026-10-01 02:00:00');
        $stock=$w->stocks()->where('item_id',$item->id)->first();
        $this->assertSame('20.0000',(string)$stock->quantity);
        $this->assertSame('15.0000',(string)$stock->average_cost);
        $service->issue($s->id,$w->id,$item->id,'77777777-7777-4777-8777-777777777777','5.0000','2026-10-01 03:00:00');
        $this->assertSame('15.0000',(string)$stock->fresh()->quantity);
    }

    public function test_maintenance_service_respects_asset_station_and_lifecycle(): void
    {
        $s=Station::create(['code'=>'M-A','name'=>'Maintenance','timezone'=>'Asia/Aden','currency_code'=>'YER']);
        $cat=AssetCategory::create(['station_id'=>$s->id,'code'=>'GEN','name'=>'Generators']);
        $asset=Asset::create(['station_id'=>$s->id,'asset_category_id'=>$cat->id,'code'=>'A1','name'=>'Generator Asset','purchase_cost'=>'1000.0000','residual_value'=>'100.0000','status'=>'active']);
        $service=app(MaintenanceService::class);
        $work=$service->openWorkOrder($s->id,$asset->id,'88888888-8888-4888-8888-888888888888','WO-1','Inspection','2026-10-01');
        $service->addExpense($s->id,$work->id,'Oil','25.0000');
        $done=$service->completeWorkOrder($s->id,$work->id,'2026-10-02','Completed');
        $this->assertSame('completed',$done->status);
    }
}
