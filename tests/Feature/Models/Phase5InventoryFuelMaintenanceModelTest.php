<?php

namespace Tests\Feature\Models;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\FuelIssue;
use App\Models\FuelReceipt;
use App\Models\FuelStockMovement;
use App\Models\FuelTank;
use App\Models\FuelType;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceWorkOrder;
use App\Models\MaintenanceWorkOrderPart;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5InventoryFuelMaintenanceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_procurement_fuel_and_maintenance_models_are_related_and_station_scoped(): void
    {
        $stationA = Station::create([
            'code' => 'IFA',
            'name' => 'Inventory Fuel Station A',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
        ]);

        $stationB = Station::create([
            'code' => 'IFB',
            'name' => 'Inventory Fuel Station B',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
        ]);

        $category = ItemCategory::create([
            'station_id' => $stationA->id,
            'code' => 'SPARE',
            'name' => 'Spare Parts',
        ]);

        $unit = UnitOfMeasure::create([
            'code' => 'PCS',
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);

        $item = Item::create([
            'station_id' => $stationA->id,
            'item_category_id' => $category->id,
            'unit_of_measure_id' => $unit->id,
            'code' => 'ITEM-001',
            'name' => 'Oil Filter',
            'item_type' => 'stock',
            'standard_cost' => '25.0000',
            'reorder_level' => '5.0000',
            'is_active' => true,
        ]);

        $itemB = Item::create([
            'station_id' => $stationB->id,
            'unit_of_measure_id' => $unit->id,
            'code' => 'ITEM-001',
            'name' => 'Oil Filter',
            'item_type' => 'stock',
            'standard_cost' => '25.0000',
            'reorder_level' => '5.0000',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'station_id' => $stationA->id,
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
            'type' => 'spares',
            'is_active' => true,
        ]);

        $stock = WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'quantity' => '10.0000',
            'average_cost' => '25.0000',
        ]);

        $movement = StockMovement::create([
            'transaction_uuid' => '66666666-6666-4666-8666-666666666666',
            'station_id' => $stationA->id,
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'movement_type' => 'receipt',
            'quantity' => '10.0000',
            'unit_cost' => '25.0000',
            'moved_at' => '2026-09-29 09:00:00',
        ]);

        $supplier = Supplier::create([
            'station_id' => $stationA->id,
            'code' => 'SUP-001',
            'name' => 'Generator Supplies',
            'status' => 'active',
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'transaction_uuid' => '77777777-7777-4777-8777-777777777777',
            'station_id' => $stationA->id,
            'number' => 'PR-0001',
            'request_date' => '2026-09-29',
            'status' => 'submitted',
        ]);

        $purchaseRequestItem = PurchaseRequestItem::create([
            'purchase_request_id' => $purchaseRequest->id,
            'item_id' => $item->id,
            'quantity' => '5.0000',
            'line_no' => 1,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'transaction_uuid' => '88888888-8888-4888-8888-888888888888',
            'station_id' => $stationA->id,
            'supplier_id' => $supplier->id,
            'purchase_request_id' => $purchaseRequest->id,
            'number' => 'PO-0001',
            'order_date' => '2026-09-29',
            'status' => 'approved',
            'total' => '125.0000',
        ]);

        $purchaseOrderItem = PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $item->id,
            'quantity' => '5.0000',
            'unit_cost' => '25.0000',
            'amount' => '125.0000',
            'line_no' => 1,
        ]);

        $goodsReceipt = GoodsReceipt::create([
            'transaction_uuid' => '99999999-9999-4999-8999-999999999999',
            'station_id' => $stationA->id,
            'purchase_order_id' => $purchaseOrder->id,
            'number' => 'GR-0001',
            'receipt_date' => '2026-09-29',
            'warehouse_id' => $warehouse->id,
            'status' => 'posted',
        ]);

        $goodsReceiptItem = GoodsReceiptItem::create([
            'goods_receipt_id' => $goodsReceipt->id,
            'item_id' => $item->id,
            'quantity' => '5.0000',
            'unit_cost' => '25.0000',
            'amount' => '125.0000',
            'line_no' => 1,
        ]);

        $supplierInvoice = SupplierInvoice::create([
            'transaction_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'station_id' => $stationA->id,
            'supplier_id' => $supplier->id,
            'goods_receipt_id' => $goodsReceipt->id,
            'number' => 'SI-0001',
            'invoice_date' => '2026-09-29',
            'due_date' => '2026-10-29',
            'subtotal' => '125.0000',
            'tax' => '0.0000',
            'total' => '125.0000',
            'paid_amount' => '50.0000',
            'status' => 'partially_paid',
        ]);

        $supplierInvoiceItem = SupplierInvoiceItem::create([
            'supplier_invoice_id' => $supplierInvoice->id,
            'item_id' => $item->id,
            'description' => 'Oil Filter',
            'quantity' => '5.0000',
            'unit_cost' => '25.0000',
            'amount' => '125.0000',
            'line_no' => 1,
        ]);

        $supplierPayment = SupplierPayment::create([
            'transaction_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'station_id' => $stationA->id,
            'supplier_id' => $supplier->id,
            'receipt_number' => 'SP-0001',
            'paid_at' => '2026-09-29 15:00:00',
            'amount' => '50.0000',
            'method' => 'cash',
            'status' => 'posted',
        ]);

        $allocation = SupplierPaymentAllocation::create([
            'supplier_payment_id' => $supplierPayment->id,
            'supplier_invoice_id' => $supplierInvoice->id,
            'amount' => '50.0000',
        ]);

        $fuelType = FuelType::create([
            'station_id' => $stationA->id,
            'code' => 'DIESEL',
            'name' => 'Diesel',
            'unit' => 'liter',
            'is_active' => true,
        ]);

        $tank = FuelTank::create([
            'station_id' => $stationA->id,
            'code' => 'TANK-001',
            'name' => 'Main Diesel Tank',
            'capacity' => '10000.0000',
            'current_quantity' => '8000.0000',
            'is_active' => true,
        ]);

        $fuelReceipt = FuelReceipt::create([
            'transaction_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'station_id' => $stationA->id,
            'fuel_type_id' => $fuelType->id,
            'fuel_tank_id' => $tank->id,
            'received_at' => '2026-09-29 08:00:00',
            'quantity' => '5000.0000',
            'unit_cost' => '1.5000',
            'total_cost' => '7500.0000',
            'status' => 'posted',
        ]);

        $fuelIssue = FuelIssue::create([
            'transaction_uuid' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'station_id' => $stationA->id,
            'fuel_type_id' => $fuelType->id,
            'fuel_tank_id' => $tank->id,
            'issued_at' => '2026-09-29 18:00:00',
            'quantity' => '1000.0000',
            'unit_cost' => '1.5000',
            'total_cost' => '1500.0000',
            'status' => 'posted',
        ]);

        $fuelMovement = FuelStockMovement::create([
            'transaction_uuid' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'station_id' => $stationA->id,
            'fuel_type_id' => $fuelType->id,
            'fuel_tank_id' => $tank->id,
            'movement_type' => 'issue',
            'quantity' => '1000.0000',
            'unit_cost' => '1.5000',
            'moved_at' => '2026-09-29 18:00:00',
        ]);

        $assetCategory = AssetCategory::create([
            'station_id' => $stationA->id,
            'code' => 'GEN',
            'name' => 'Generators',
        ]);

        $asset = Asset::create([
            'station_id' => $stationA->id,
            'asset_category_id' => $assetCategory->id,
            'code' => 'AST-001',
            'name' => 'Generator Asset',
            'serial_number' => 'GEN-SERIAL-001',
            'acquired_on' => '2026-01-01',
            'purchase_cost' => '100000.0000',
            'residual_value' => '10000.0000',
            'useful_life_months' => 120,
            'depreciation_start_on' => '2026-01-01',
            'status' => 'active',
        ]);

        $plan = MaintenancePlan::create([
            'station_id' => $stationA->id,
            'asset_id' => $asset->id,
            'name' => 'Monthly Maintenance',
            'interval_days' => 30,
            'next_due_on' => '2026-10-29',
            'is_active' => true,
        ]);

        $workOrder = MaintenanceWorkOrder::create([
            'transaction_uuid' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'station_id' => $stationA->id,
            'asset_id' => $asset->id,
            'number' => 'WO-0001',
            'title' => 'Replace oil filter',
            'type' => 'preventive',
            'priority' => 'normal',
            'status' => 'open',
            'opened_on' => '2026-09-29',
        ]);

        $workOrderPart = MaintenanceWorkOrderPart::create([
            'maintenance_work_order_id' => $workOrder->id,
            'item_id' => $item->id,
            'quantity' => '1.0000',
            'unit_cost' => '25.0000',
            'amount' => '25.0000',
        ]);

        $item->load([
            'category',
            'unitOfMeasure',
            'warehouseStocks.warehouse',
            'stockMovements',
            'purchaseRequestItems.purchaseRequest',
            'purchaseOrderItems.purchaseOrder',
            'goodsReceiptItems.goodsReceipt',
            'supplierInvoiceItems.supplierInvoice',
            'maintenanceParts.workOrder',
        ]);
        $purchaseRequest->load(['items.item', 'purchaseOrders']);
        $purchaseOrder->load(['supplier', 'purchaseRequest', 'items.item', 'goodsReceipts']);
        $goodsReceipt->load(['purchaseOrder', 'warehouse', 'items.item', 'supplierInvoices']);
        $supplierInvoice->load(['supplier', 'goodsReceipt', 'items.item', 'payments']);
        $supplierPayment->load(['supplier', 'cashAccount', 'allocations.supplierInvoice']);
        $tank->load(['receipts.fuelType', 'issues.fuelType', 'stockMovements.fuelType']);
        $asset->load(['category', 'maintenancePlans', 'workOrders']);
        $workOrder->load(['asset', 'parts.item']);
        $fuelReceipt->load(['fuelType', 'fuelTank']);
        $fuelIssue->load(['fuelType', 'fuelTank', 'generator']);

        $this->assertSame([$item->id], Item::forStation($stationA->id)->pluck('id')->all());
        $this->assertNotContains($itemB->id, Item::forStation($stationA->id)->pluck('id')->all());

        $this->assertSame($category->id, $item->category->id);
        $this->assertSame($unit->id, $item->unitOfMeasure->id);
        $this->assertSame($stock->id, $item->warehouseStocks->first()->id);
        $this->assertSame($movement->id, $item->stockMovements->first()->id);
        $this->assertSame($purchaseRequestItem->id, $item->purchaseRequestItems->first()->id);
        $this->assertSame($purchaseOrderItem->id, $item->purchaseOrderItems->first()->id);
        $this->assertSame($goodsReceiptItem->id, $item->goodsReceiptItems->first()->id);
        $this->assertSame($supplierInvoiceItem->id, $item->supplierInvoiceItems->first()->id);
        $this->assertSame($workOrderPart->id, $item->maintenanceParts->first()->id);

        $this->assertSame($supplier->id, $purchaseOrder->supplier->id);
        $this->assertSame($purchaseRequest->id, $purchaseOrder->purchaseRequest->id);
        $this->assertSame($goodsReceipt->id, $purchaseOrder->goodsReceipts->first()->id);
        $this->assertSame($warehouse->id, $goodsReceipt->warehouse->id);
        $this->assertSame($supplier->id, $supplierInvoice->supplier->id);
        $this->assertSame($goodsReceipt->id, $supplierInvoice->goodsReceipt->id);
        $this->assertSame($supplierPayment->id, $supplierInvoice->payments->first()->id);
        $this->assertSame($allocation->id, $supplierPayment->allocations->first()->id);

        $this->assertSame($fuelType->id, $fuelReceipt->fuelType->id);
        $this->assertSame($tank->id, $fuelReceipt->fuelTank->id);
        $this->assertSame($fuelType->id, $fuelIssue->fuelType->id);
        $this->assertSame($tank->id, $fuelIssue->fuelTank->id);
        $this->assertSame($fuelType->id, $tank->stockMovements->first()->fuelType->id);

        $this->assertSame($assetCategory->id, $asset->category->id);
        $this->assertSame($plan->id, $asset->maintenancePlans->first()->id);
        $this->assertSame($workOrder->id, $asset->workOrders->first()->id);
        $this->assertSame($asset->id, $workOrder->asset->id);
        $this->assertSame($item->id, $workOrder->parts->first()->item->id);
    }
}
