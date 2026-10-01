<?php

namespace Tests\Feature\Models;

use App\Models\BillingCycle;
use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Station;
use App\Models\Tariff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase5CoreModelTest extends TestCase
{
    use RefreshDatabase;

    private function station(): Station
    {
        return Station::create([
            'code' => 'ST-'.Str::upper(Str::random(8)),
            'name' => 'Test Station',
            'name_ar' => 'محطة اختبار',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);
    }

    public function test_station_scope_isolates_customers(): void
    {
        $a=$this->station();
        $b=$this->station();

        Customer::create(['station_id'=>$a->id,'code'=>'C-A','name'=>'A','status'=>'active','opening_balance'=>'0']);
        Customer::create(['station_id'=>$b->id,'code'=>'C-B','name'=>'B','status'=>'active','opening_balance'=>'0']);

        $this->assertSame(['C-A'], Customer::forStation($a->id)->pluck('code')->all());
        $this->assertSame(['C-B'], Customer::forStation($b->id)->pluck('code')->all());
    }

    public function test_meter_reading_billing_invoice_relationships(): void
    {
        $station=$this->station();

        $customer=Customer::create([
            'station_id'=>$station->id,'code'=>'C-001','name'=>'Customer 001',
            'status'=>'active','opening_balance'=>'0'
        ]);

        $meter=Meter::create([
            'station_id'=>$station->id,'customer_id'=>$customer->id,'serial_number'=>'M-001',
            'meter_type'=>'energy','multiplier'=>'1','initial_reading'=>'0','status'=>'active'
        ]);

        $reading=MeterReading::create([
            'transaction_uuid'=>(string)Str::uuid(),'station_id'=>$station->id,'meter_id'=>$meter->id,
            'reading_at'=>now(),'reading_value'=>'100','previous_reading_value'=>'80',
            'consumption'=>'20','source'=>'manual','status'=>'validated'
        ]);

        $tariff=Tariff::create([
            'station_id'=>$station->id,'code'=>'T-001','name'=>'Standard',
            'effective_from'=>'2026-09-01','is_active'=>true
        ]);

        $period=BillingPeriod::create([
            'station_id'=>$station->id,'code'=>'BP-001','name'=>'September',
            'starts_on'=>'2026-09-01','ends_on'=>'2026-09-30','status'=>'open'
        ]);

        $cycle=BillingCycle::create([
            'station_id'=>$station->id,'billing_period_id'=>$period->id,'code'=>'BC-001',
            'name'=>'Cycle 1','reading_from'=>'2026-09-01','reading_to'=>'2026-09-29',
            'status'=>'completed'
        ]);

        $invoice=Invoice::create([
            'transaction_uuid'=>(string)Str::uuid(),'station_id'=>$station->id,
            'customer_id'=>$customer->id,'meter_id'=>$meter->id,'reading_id'=>$reading->id,
            'tariff_id'=>$tariff->id,'billing_cycle_id'=>$cycle->id,'number'=>'INV-001',
            'invoice_date'=>'2026-09-29','previous_reading'=>'80','current_reading'=>'100',
            'consumption'=>'20','subtotal'=>'1000','discount'=>'0','tax'=>'0','total'=>'1000',
            'paid_amount'=>'0','status'=>'issued'
        ]);

        InvoiceItem::create([
            'invoice_id'=>$invoice->id,'quantity'=>'20','unit_rate'=>'50',
            'amount'=>'1000','description'=>'Energy','line_no'=>1
        ]);

        $fresh=$invoice->fresh('customer','meter','reading','billingCycle','items');

        $this->assertSame($customer->id,$fresh->customer->id);
        $this->assertSame($meter->id,$fresh->meter->id);
        $this->assertSame($reading->id,$fresh->reading->id);
        $this->assertSame($cycle->id,$fresh->billingCycle->id);
        $this->assertCount(1,$fresh->items);
    }
}
