<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\GoodsReceiptResource;
use App\Filament\Resources\SupplierInvoiceResource;
use App\Filament\Resources\SupplierPaymentResource;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\Station;
use App\Models\User;
use App\Support\StationContext;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6ProcurementOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_transaction_resources_are_authorized_and_draft_only_for_editing(): void
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

        $draftReceipt = GoodsReceipt::create([
            'transaction_uuid'=>(string)\Illuminate\Support\Str::uuid(),
            'station_id'=>$stationA->id,'number'=>'GR-A','receipt_date'=>'2026-09-29',
            'warehouse_id'=>1,'status'=>'draft',
        ]);

        $postedReceipt = GoodsReceipt::create([
            'transaction_uuid'=>(string)\Illuminate\Support\Str::uuid(),
            'station_id'=>$stationA->id,'number'=>'GR-B','receipt_date'=>'2026-09-29',
            'warehouse_id'=>1,'status'=>'posted',
        ]);

        $draftInvoice = SupplierInvoice::create([
            'transaction_uuid'=>(string)\Illuminate\Support\Str::uuid(),
            'station_id'=>$stationA->id,'supplier_id'=>1,'number'=>'SI-A',
            'invoice_date'=>'2026-09-29','subtotal'=>'1.0000','tax'=>'0.0000',
            'total'=>'1.0000','paid_amount'=>'0.0000','status'=>'draft',
        ]);

        $postedInvoice = SupplierInvoice::create([
            'transaction_uuid'=>(string)\Illuminate\Support\Str::uuid(),
            'station_id'=>$stationA->id,'supplier_id'=>1,'number'=>'SI-B',
            'invoice_date'=>'2026-09-29','subtotal'=>'1.0000','tax'=>'0.0000',
            'total'=>'1.0000','paid_amount'=>'0.0000','status'=>'posted',
        ]);

        $draftReceipt->warehouse_id = null;
        $draftReceipt->save();
        $postedReceipt->warehouse_id = null;
        $postedReceipt->save();
        $draftInvoice->supplier_id = null;
        $draftInvoice->save();
        $postedInvoice->supplier_id = null;
        $postedInvoice->save();

        $user=User::factory()->create(['is_active'=>true]);
        $user->assignRole('storekeeper');
        $user->stations()->attach($stationA->id,['is_default'=>true]);
        $user->stations()->attach($stationB->id,['is_default'=>false]);

        $this->actingAs($user);

        $this->assertTrue(GoodsReceiptResource::canCreate());
        $this->assertTrue(SupplierInvoiceResource::canCreate());
        $this->assertTrue(SupplierPaymentResource::canCreate());
        $this->assertTrue(GoodsReceiptResource::canEdit($draftReceipt));
        $this->assertFalse(GoodsReceiptResource::canEdit($postedReceipt));
        $this->assertTrue(SupplierInvoiceResource::canEdit($draftInvoice));
        $this->assertFalse(SupplierInvoiceResource::canEdit($postedInvoice));

        app(StationContext::class)->set($stationB->id);
        $this->assertSame([], GoodsReceiptResource::getEloquentQuery()->pluck('number')->all());
        $this->assertSame([], SupplierInvoiceResource::getEloquentQuery()->pluck('number')->all());
        $this->assertSame([], SupplierPaymentResource::getEloquentQuery()->pluck('receipt_number')->all());
    }
}
