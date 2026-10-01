<?php

namespace Tests\Feature\Billing;

use App\Models\BillingCycle;
use App\Models\BillingPeriod;
use App\Models\Station;
use App\Services\Billing\BillingCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BillingCycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_must_be_inside_open_period_and_can_be_completed(): void
    {
        $station = Station::create(['code'=>'S-BILL','name'=>'Billing Station','currency_code'=>'YER','is_active'=>true]);
        $service = app(BillingCycleService::class);

        $period = $service->openPeriod($station->id,'2026-10','October 2026','2026-10-01','2026-10-31');
        $cycle = $service->createCycle($station->id,$period->id,'C-01','October Cycle','2026-10-01','2026-10-31','2026-10-31');

        $this->assertSame('draft',$cycle->status);
        $service->startCycle($station->id,$cycle->id);
        $completed = $service->completeCycle($station->id,$cycle->id);

        $this->assertSame('completed',$completed->status);
        $this->assertDatabaseHas('billing_cycles',['id'=>$cycle->id,'station_id'=>$station->id,'status'=>'completed']);
    }

    public function test_period_cannot_close_with_unfinished_cycle(): void
    {
        $station = Station::create(['code'=>'S-BILL2','name'=>'Billing Station 2','currency_code'=>'YER','is_active'=>true]);
        $service = app(BillingCycleService::class);
        $period = $service->openPeriod($station->id,'2026-11','November 2026','2026-11-01','2026-11-30');
        $service->createCycle($station->id,$period->id,'C-01','Cycle','2026-11-01','2026-11-30');

        $this->expectException(RuntimeException::class);
        $service->closePeriod($station->id,$period->id);
    }

    public function test_station_isolation_is_enforced_for_cycles(): void
    {
        $a=Station::create(['code'=>'S-A','name'=>'A','currency_code'=>'YER','is_active'=>true]);
        $b=Station::create(['code'=>'S-B','name'=>'B','currency_code'=>'YER','is_active'=>true]);
        $service=app(BillingCycleService::class);
        $period=$service->openPeriod($a->id,'2026-12','December','2026-12-01','2026-12-31');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->createCycle($b->id,$period->id,'C-01','Cross Station','2026-12-01','2026-12-31');
    }
}
