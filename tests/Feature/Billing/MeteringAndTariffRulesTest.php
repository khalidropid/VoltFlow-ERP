<?php

namespace Tests\Feature\Billing;

use App\Models\Customer;
use App\Models\Meter;
use App\Models\Station;
use App\Models\Tariff;
use App\Services\Billing\MeteringFoundationService;
use App\Services\Billing\TariffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MeteringAndTariffRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_have_two_active_meter_installations(): void
    {
        [$station,$customer,$meter1,$meter2]=$this->fixture();
        $service=app(MeteringFoundationService::class);
        $service->installMeter($station->id,$meter1->id,$customer->id,'2026-10-01','0');
        $this->expectException(RuntimeException::class);
        $service->installMeter($station->id,$meter2->id,$customer->id,'2026-10-02','0');
    }

    public function test_customer_tariff_assignments_cannot_overlap(): void
    {
        [$station,$customer]=array_slice($this->fixture(),0,2);
        $t1=Tariff::create(['station_id'=>$station->id,'code'=>'T1','name'=>'Tariff 1','effective_from'=>'2026-01-01','is_active'=>true]);
        $t2=Tariff::create(['station_id'=>$station->id,'code'=>'T2','name'=>'Tariff 2','effective_from'=>'2026-01-01','is_active'=>true]);
        $service=app(MeteringFoundationService::class);
        $service->assignTariff($station->id,$customer->id,$t1->id,'2026-01-01','2026-06-30');
        $this->expectException(RuntimeException::class);
        $service->assignTariff($station->id,$customer->id,$t2->id,'2026-06-15','2026-12-31');
    }

    public function test_tariff_slabs_must_be_contiguous_and_open_ended_last(): void
    {
        $station=Station::create(['code'=>'TS-1','name'=>'Tariff Station','currency_code'=>'YER','is_active'=>true]);
        $tariff=Tariff::create(['station_id'=>$station->id,'code'=>'T1','name'=>'Tariff','effective_from'=>'2026-01-01','is_active'=>true]);
        $service=app(TariffService::class);
        $this->expectException(RuntimeException::class);
        $service->replaceSlabs($station->id,$tariff->id,[
            ['from_unit'=>'0','to_unit'=>'100','rate'=>'10','sort_order'=>1],
            ['from_unit'=>'101','to_unit'=>null,'rate'=>'20','sort_order'=>2],
        ]);
    }

    private function fixture(): array
    {
        $station=Station::create(['code'=>'MTR-'.uniqid(),'name'=>'Meter Station','currency_code'=>'YER','is_active'=>true]);
        $customer=Customer::create(['station_id'=>$station->id,'code'=>'C-'.uniqid(),'name'=>'Customer']);
        $meter1=Meter::create(['station_id'=>$station->id,'customer_id'=>$customer->id,'serial_number'=>'M1-'.uniqid(),'meter_type'=>'electric','multiplier'=>'1','initial_reading'=>'0','status'=>'active']);
        $meter2=Meter::create(['station_id'=>$station->id,'customer_id'=>$customer->id,'serial_number'=>'M2-'.uniqid(),'meter_type'=>'electric','multiplier'=>'1','initial_reading'=>'0','status'=>'active']);
        return [$station,$customer,$meter1,$meter2];
    }
}
