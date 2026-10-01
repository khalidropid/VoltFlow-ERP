<?php

namespace Tests\Feature\Domain;

use App\Models\Station;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Collections\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainControlRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_allocation_cannot_cross_station_or_customer(): void
    {
        $a=Station::create(['code'=>'REG-A','name'=>'A','currency_code'=>'YER','is_active'=>true]);
        $b=Station::create(['code'=>'REG-B','name'=>'B','currency_code'=>'YER','is_active'=>true]);
        $ca=Customer::create(['station_id'=>$a->id,'code'=>'CA','name'=>'A']);
        $cb=Customer::create(['station_id'=>$b->id,'code'=>'CB','name'=>'B']);
        $invoice=Invoice::create(['station_id'=>$b->id,'customer_id'=>$cb->id,'number'=>'INV-B','invoice_date'=>'2026-10-01','due_date'=>'2026-10-31','subtotal'=>'100','total'=>'100','status'=>'issued','paid_amount'=>'0']);
        $cash=CashAccount::create(['station_id'=>$a->id,'code'=>'C','name'=>'Cash','type'=>'cash','balance'=>'0','is_active'=>true]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(PaymentService::class)->allocate($a->id,999,$invoice->id,'10');
    }
}