<?php

namespace Tests\Feature\Procurement;

use App\Models\CashAccount;
use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\Station;
use App\Models\Supplier;
use App\Models\SupplierAccountLink;
use App\Models\SupplierInvoice;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Procurement\GoodsReceiptService;
use App\Services\Procurement\ProcurementException;
use App\Services\Procurement\SupplierInvoiceService;
use App\Services\Procurement\SupplierPaymentService;
use App\Support\Decimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierProcurementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_goods_receipt_updates_weighted_average_stock_and_purchase_order_status(): void
    {
        $station = $this->station('ST-A');
        $uom = UnitOfMeasure::create(['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs']);

        $inventoryAccount = $this->account($station, '1300', 'Inventory', 'asset');
        $item = Item::create([
            'station_id' => $station->id,
            'unit_of_measure_id' => $uom->id,
            'code' => 'ITEM-A',
            'name' => 'Item A',
            'item_type' => 'stock',
            'standard_cost' => '8.0000',
            'reorder_level' => '0.0000',
            'inventory_account_id' => $inventoryAccount->id,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'station_id' => $station->id,
            'code' => 'WH-A',
            'name' => 'Main Warehouse',
            'type' => 'main',
            'is_active' => true,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'quantity' => '10.0000',
            'average_cost' => '8.0000',
        ]);

        $supplier = Supplier::create([
            'station_id' => $station->id,
            'code' => 'SUP-A',
            'name' => 'Supplier A',
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'transaction_uuid' => (string) Str::uuid(),
            'station_id' => $station->id,
            'supplier_id' => $supplier->id,
            'number' => 'PO-A',
            'order_date' => '2026-09-29',
            'status' => 'approved',
            'total' => '120.0000',
        ]);

        $po->items()->create([
            'item_id' => $item->id,
            'quantity' => '10.0000',
            'unit_cost' => '12.0000',
            'amount' => '120.0000',
            'line_no' => 1,
        ]);

        $receipt = GoodsReceipt::create([
            'transaction_uuid' => (string) Str::uuid(),
            'station_id' => $station->id,
            'purchase_order_id' => $po->id,
            'number' => 'GR-A',
            'receipt_date' => '2026-09-29',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $receipt->items()->create([
            'item_id' => $item->id,
            'quantity' => '10.0000',
            'unit_cost' => '12.0000',
            'amount' => '120.0000',
            'line_no' => 1,
        ]);

        $posted = app(GoodsReceiptService::class)->post($receipt->id);

        $stock = WarehouseStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('item_id', $item->id)
            ->firstOrFail();

        $this->assertSame('posted', $posted->status);
        $this->assertSame('20.0000', (string) $stock->quantity);
        $this->assertSame('10.0000', (string) $stock->average_cost);
        $this->assertSame(1, $warehouse->stockMovements()->where('reference_id', $receipt->id)->count());
        $this->assertSame('received', $po->fresh()->status);

        $replayed = app(GoodsReceiptService::class)->post($receipt->id);

        $this->assertSame($receipt->id, $replayed->id);
        $this->assertSame(1, $warehouse->stockMovements()->where('reference_id', $receipt->id)->count());
        $this->assertSame('20.0000', (string) $stock->fresh()->quantity);
    }

    public function test_supplier_invoice_posts_inventory_tax_and_payable_once(): void
    {
        [$station, $uom, $item, $warehouse, $supplier, $receipt] = $this->postedReceiptFixture();

        $inventoryAccount = $item->inventoryAccount()->firstOrFail();
        $payableAccount = $this->account($station, '2100', 'Accounts Payable', 'liability');
        $taxAccount = $this->account($station, '1400', 'Input Tax', 'asset');
        $expenseAccount = $this->account($station, '5100', 'Procurement Expense', 'expense');

        SupplierAccountLink::create([
            'station_id' => $station->id,
            'supplier_id' => $supplier->id,
            'payable_account_id' => $payableAccount->id,
            'expense_account_id' => $expenseAccount->id,
            'tax_account_id' => $taxAccount->id,
        ]);

        $this->fiscalPeriod($station);

        $invoice = SupplierInvoice::create([
            'transaction_uuid' => (string) Str::uuid(),
            'station_id' => $station->id,
            'supplier_id' => $supplier->id,
            'goods_receipt_id' => $receipt->id,
            'number' => 'SI-A',
            'invoice_date' => '2026-09-29',
            'due_date' => '2026-10-15',
            'subtotal' => '120.0000',
            'tax' => '12.0000',
            'total' => '132.0000',
            'paid_amount' => '0.0000',
            'status' => 'draft',
        ]);

        $invoice->items()->create([
            'item_id' => $item->id,
            'description' => 'Item A',
            'quantity' => '10.0000',
            'unit_cost' => '12.0000',
            'amount' => '120.0000',
            'line_no' => 1,
        ]);

        $posted = app(SupplierInvoiceService::class)->post($invoice->id);

        $entry = $posted->journalEntry()->with('lines')->firstOrFail();

        $this->assertSame('posted', $posted->status);
        $this->assertNotNull($posted->journal_entry_id);
        $this->assertSame(3, $entry->lines->count());
        $this->assertSame('120.0000', (string) $entry->lines->where('account_id', $inventoryAccount->id)->first()->debit);
        $this->assertSame('12.0000', (string) $entry->lines->where('account_id', $taxAccount->id)->first()->debit);
        $this->assertSame('132.0000', (string) $entry->lines->where('account_id', $payableAccount->id)->first()->credit);
        $this->assertSame('132.0000', $this->journalDebitTotal($entry));
        $this->assertSame('132.0000', $this->journalCreditTotal($entry));

        $replayed = app(SupplierInvoiceService::class)->post($invoice->id);

        $this->assertSame($posted->id, $replayed->id);
        $this->assertSame($entry->id, $replayed->journal_entry_id);
        $this->assertSame(1, JournalEntry::query()->where('source_type', 'supplier_invoice')->where('source_id', $invoice->id)->count());
    }

    public function test_supplier_payment_is_idempotent_and_void_restores_invoice_and_cash(): void
    {
        [$station, $uom, $item, $warehouse, $supplier, $receipt] = $this->postedReceiptFixture();

        $inventoryAccount = $item->inventoryAccount()->firstOrFail();
        $payableAccount = $this->account($station, '2100', 'Accounts Payable', 'liability');
        $taxAccount = $this->account($station, '1400', 'Input Tax', 'asset');
        $expenseAccount = $this->account($station, '5100', 'Procurement Expense', 'expense');
        $cashGlAccount = $this->account($station, '1000', 'Cash', 'asset');

        SupplierAccountLink::create([
            'station_id' => $station->id,
            'supplier_id' => $supplier->id,
            'payable_account_id' => $payableAccount->id,
            'expense_account_id' => $expenseAccount->id,
            'tax_account_id' => $taxAccount->id,
        ]);

        $this->fiscalPeriod($station);

        $invoice = SupplierInvoice::create([
            'transaction_uuid' => (string) Str::uuid(),
            'station_id' => $station->id,
            'supplier_id' => $supplier->id,
            'goods_receipt_id' => $receipt->id,
            'number' => 'SI-A',
            'invoice_date' => '2026-09-29',
            'due_date' => '2026-10-15',
            'subtotal' => '120.0000',
            'tax' => '12.0000',
            'total' => '132.0000',
            'paid_amount' => '0.0000',
            'status' => 'draft',
        ]);

        $invoice->items()->create([
            'item_id' => $item->id,
            'description' => 'Item A',
            'quantity' => '10.0000',
            'unit_cost' => '12.0000',
            'amount' => '120.0000',
            'line_no' => 1,
        ]);

        $invoice = app(SupplierInvoiceService::class)->post($invoice->id);

        $cash = CashAccount::create([
            'station_id' => $station->id,
            'code' => 'CASH-01',
            'name' => 'Main Cash',
            'type' => 'cash',
            'account_id' => $cashGlAccount->id,
            'balance' => '500.0000',
            'is_active' => true,
        ]);

        $service = app(SupplierPaymentService::class);
        $uuid = (string) Str::uuid();

        $payment = $service->pay(
            $station->id,
            $supplier->id,
            $cash->id,
            $uuid,
            'SP-A',
            '2026-09-29 10:00:00',
            '132.0000',
            [['supplier_invoice_id' => $invoice->id, 'amount' => '132.0000']],
            'cash'
        );

        $replay = $service->pay(
            $station->id,
            $supplier->id,
            $cash->id,
            $uuid,
            'SP-A',
            '2026-09-29 10:00:00',
            '132.0000',
            [['supplier_invoice_id' => $invoice->id, 'amount' => '132.0000']],
            'cash'
        );

        $paymentEntry = $payment->journalEntry()->with('lines')->firstOrFail();

        $this->assertSame($payment->id, $replay->id);
        $this->assertSame(1, $payment->allocations()->count());
        $this->assertSame('368.0000', (string) $cash->fresh()->balance);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('132.0000', (string) $invoice->fresh()->paid_amount);
        $this->assertSame('132.0000', $this->journalDebitTotal($paymentEntry));
        $this->assertSame('132.0000', $this->journalCreditTotal($paymentEntry));

        $voided = $service->void($payment->id, '2026-09-29 11:00:00');

        $this->assertSame('voided', $voided->status);
        $this->assertSame('500.0000', (string) $cash->fresh()->balance);
        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertSame('0.0000', (string) $invoice->fresh()->paid_amount);
        $this->assertNotNull($voided->reversal_journal_entry_id);

        $reversal = $voided->reversalJournalEntry()->with('lines')->firstOrFail();
        $this->assertSame('132.0000', $this->journalDebitTotal($reversal));
        $this->assertSame('132.0000', $this->journalCreditTotal($reversal));

        $voidReplay = $service->void($payment->id, '2026-09-29 11:00:00');

        $this->assertSame($voided->id, $voidReplay->id);
        $this->assertSame($voided->reversal_journal_entry_id, $voidReplay->reversal_journal_entry_id);
        $this->assertSame('500.0000', (string) $cash->fresh()->balance);
        $this->assertSame(1, JournalEntry::query()->where('source_type', 'supplier_payment')->where('source_id', $payment->id)->count());
        $this->assertSame(1, JournalEntry::query()->where('source_type', 'supplier_payment_reversal')->where('source_id', $payment->id)->count());
    }

    private function postedReceiptFixture(): array
    {
        $station = $this->station('ST-A');
        $uom = UnitOfMeasure::create(['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs']);

        $inventoryAccount = $this->account($station, '1300', 'Inventory', 'asset');
        $item = Item::create([
            'station_id' => $station->id,
            'unit_of_measure_id' => $uom->id,
            'code' => 'ITEM-A',
            'name' => 'Item A',
            'item_type' => 'stock',
            'standard_cost' => '12.0000',
            'reorder_level' => '0.0000',
            'inventory_account_id' => $inventoryAccount->id,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'station_id' => $station->id,
            'code' => 'WH-A',
            'name' => 'Main Warehouse',
            'type' => 'main',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'station_id' => $station->id,
            'code' => 'SUP-A',
            'name' => 'Supplier A',
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'transaction_uuid' => (string) Str::uuid(),
            'station_id' => $station->id,
            'supplier_id' => $supplier->id,
            'number' => 'PO-A',
            'order_date' => '2026-09-29',
            'status' => 'approved',
            'total' => '120.0000',
        ]);

        $po->items()->create([
            'item_id' => $item->id,
            'quantity' => '10.0000',
            'unit_cost' => '12.0000',
            'amount' => '120.0000',
            'line_no' => 1,
        ]);

        $receipt = GoodsReceipt::create([
            'transaction_uuid' => (string) Str::uuid(),
            'station_id' => $station->id,
            'purchase_order_id' => $po->id,
            'number' => 'GR-A',
            'receipt_date' => '2026-09-29',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $receipt->items()->create([
            'item_id' => $item->id,
            'quantity' => '10.0000',
            'unit_cost' => '12.0000',
            'amount' => '120.0000',
            'line_no' => 1,
        ]);

        $receipt = app(GoodsReceiptService::class)->post($receipt->id);

        return [$station, $uom, $item, $warehouse, $supplier, $receipt];
    }

    private function station(string $code): Station
    {
        return Station::create([
            'code' => $code,
            'name' => 'Station A',
            'name_ar' => 'المحطة أ',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
            'is_active' => true,
        ]);
    }

    private function account(Station $station, string $code, string $name, string $type): ChartOfAccount
    {
        return ChartOfAccount::create([
            'station_id' => $station->id,
            'code' => $code,
            'name' => $name,
            'name_ar' => $name,
            'type' => $type,
            'is_postable' => true,
            'is_active' => true,
        ]);
    }

    private function fiscalPeriod(Station $station): FiscalPeriod
    {
        return FiscalPeriod::create([
            'station_id' => $station->id,
            'name' => '2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'status' => 'open',
        ]);
    }

    private function journalDebitTotal(JournalEntry $entry): string
    {
        return $entry->lines->reduce(
            fn (string $carry, $line): string => Decimal::add($carry, Decimal::normalize((string) $line->debit)),
            '0.0000'
        );
    }

    private function journalCreditTotal(JournalEntry $entry): string
    {
        return $entry->lines->reduce(
            fn (string $carry, $line): string => Decimal::add($carry, Decimal::normalize((string) $line->credit)),
            '0.0000'
        );
    }
}
