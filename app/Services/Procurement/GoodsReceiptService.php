<?php

namespace App\Services\Procurement;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WarehouseStock;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoodsReceiptService
{
    public function post(int $goodsReceiptId, ?User $actor = null): GoodsReceipt
    {
        return DB::transaction(function () use ($goodsReceiptId, $actor) {
            $receipt = GoodsReceipt::query()
                ->lockForUpdate()
                ->findOrFail($goodsReceiptId);

            if ($receipt->status === 'posted') {
                return $receipt->fresh(['purchaseOrder.items', 'items.item', 'warehouse']);
            }

            if ($receipt->status !== 'draft') {
                throw new ProcurementException('Only draft goods receipts can be posted.');
            }

            $warehouse = $receipt->warehouse()->lockForUpdate()->firstOrFail();

            if ($warehouse->station_id !== $receipt->station_id || ! $warehouse->is_active) {
                throw new ProcurementException('Goods receipt warehouse does not belong to the receipt station or is inactive.');
            }

            $items = $receipt->items()
                ->orderBy('line_no')
                ->get();

            if ($items->isEmpty()) {
                throw new ProcurementException('Goods receipt must contain at least one item.');
            }

            $itemIds = [];

            foreach ($items as $line) {
                if (isset($itemIds[$line->item_id])) {
                    throw new ProcurementException('The same item cannot appear more than once in a goods receipt.');
                }

                $itemIds[$line->item_id] = true;

                $item = $line->item()->firstOrFail();

                if ($item->station_id !== $receipt->station_id || ! $item->is_active) {
                    throw new ProcurementException('Goods receipt contains an item from another station or an inactive item.');
                }

                $quantity = Decimal::normalize((string) $line->quantity);
                $unitCost = Decimal::normalize((string) $line->unit_cost);
                $amount = Decimal::normalize((string) $line->amount);

                if (Decimal::compare($quantity, '0.0000') <= 0) {
                    throw new ProcurementException('Goods receipt quantities must be greater than zero.');
                }

                $expectedAmount = $this->roundedProduct($quantity, $unitCost);

                if (Decimal::compare($amount, $expectedAmount) !== 0) {
                    throw new ProcurementException('Goods receipt line amount does not match quantity multiplied by unit cost.');
                }
            }

            $purchaseOrder = null;

            if ($receipt->purchase_order_id !== null) {
                $purchaseOrder = PurchaseOrder::query()
                    ->lockForUpdate()
                    ->findOrFail($receipt->purchase_order_id);

                if ($purchaseOrder->station_id !== $receipt->station_id) {
                    throw new ProcurementException('Purchase order does not belong to the goods receipt station.');
                }

                if (! in_array($purchaseOrder->status, ['approved', 'sent', 'partially_received'], true)) {
                    throw new ProcurementException('Purchase order is not open for goods receipt.');
                }

                $this->validateAgainstPurchaseOrder($receipt, $purchaseOrder, $items);
            }

            foreach ($items as $line) {
                $quantity = Decimal::normalize((string) $line->quantity);
                $unitCost = Decimal::normalize((string) $line->unit_cost);

                $stock = WarehouseStock::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('item_id', $line->item_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    $stock = new WarehouseStock([
                        'warehouse_id' => $warehouse->id,
                        'item_id' => $line->item_id,
                        'quantity' => '0.0000',
                        'average_cost' => '0.0000',
                    ]);
                }

                $oldQuantity = Decimal::normalize((string) $stock->quantity);
                $oldAverageCost = Decimal::normalize((string) $stock->average_cost);
                $newQuantity = Decimal::add($oldQuantity, $quantity);

                $newAverageCost = Decimal::compare($oldQuantity, '0.0000') === 0
                    ? $unitCost
                    : $this->weightedAverage(
                        $oldQuantity,
                        $oldAverageCost,
                        $quantity,
                        $unitCost,
                        $newQuantity
                    );

                $stock->quantity = $newQuantity;
                $stock->average_cost = $newAverageCost;
                $stock->save();

                StockMovement::create([
                    'transaction_uuid' => (string) Str::uuid(),
                    'station_id' => $receipt->station_id,
                    'warehouse_id' => $warehouse->id,
                    'item_id' => $line->item_id,
                    'movement_type' => 'receipt',
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $receipt->id,
                    'moved_at' => $receipt->receipt_date->copy()->startOfDay(),
                    'created_by' => $actor?->id,
                    'notes' => 'Goods receipt ' . $receipt->number,
                ]);
            }

            if ($purchaseOrder) {
                $this->refreshPurchaseOrderStatus($purchaseOrder);
            }

            $receipt->update(['status' => 'posted']);

            $auditLogger = app(\App\Services\Audit\AuditLogger::class);
            $auditLogger->record(
                'goods_receipt.posted',
                $receipt,
                ['status' => 'draft'],
                $receipt->only(['status', 'purchase_order_id', 'warehouse_id']),
                $receipt->station_id
            );

            return $receipt->fresh(['purchaseOrder.items', 'items.item', 'warehouse']);
        }, 3);
    }

    private function validateAgainstPurchaseOrder(
        GoodsReceipt $receipt,
        PurchaseOrder $purchaseOrder,
        $receiptItems
    ): void {
        $orderedItems = $purchaseOrder->items()->get()->keyBy('item_id');

        foreach ($receiptItems as $line) {
            $ordered = $orderedItems->get($line->item_id);

            if (! $ordered) {
                throw new ProcurementException('Goods receipt contains an item that is not present on the purchase order.');
            }

            $alreadyReceived = GoodsReceiptItem::query()
                ->where('item_id', $line->item_id)
                ->whereHas('goodsReceipt', function ($query) use ($purchaseOrder) {
                    $query->where('purchase_order_id', $purchaseOrder->id)
                        ->where('status', 'posted');
                })
                ->pluck('quantity')
                ->reduce(
                    fn (string $carry, $quantity): string => Decimal::add(
                        $carry,
                        Decimal::normalize((string) $quantity)
                    ),
                    '0.0000'
                );

            $orderedQuantity = Decimal::normalize((string) $ordered->quantity);

            if (Decimal::compare($alreadyReceived, $orderedQuantity) > 0) {
                throw new ProcurementException('Purchase order already contains an over-received quantity.');
            }

            $remaining = Decimal::sub($orderedQuantity, $alreadyReceived);
            $receiptQuantity = Decimal::normalize((string) $line->quantity);

            if (Decimal::compare($receiptQuantity, $remaining) > 0) {
                throw new ProcurementException('Goods receipt quantity exceeds the remaining purchase order quantity.');
            }
        }
    }

    private function refreshPurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $orderedItems = $purchaseOrder->items()->get();

        $allReceived = true;
        $anyReceived = false;

        foreach ($orderedItems as $ordered) {
            $received = GoodsReceiptItem::query()
                ->where('item_id', $ordered->item_id)
                ->whereHas('goodsReceipt', function ($query) use ($purchaseOrder) {
                    $query->where('purchase_order_id', $purchaseOrder->id)
                        ->where('status', 'posted');
                })
                ->pluck('quantity')
                ->reduce(
                    fn (string $carry, $quantity): string => Decimal::add(
                        $carry,
                        Decimal::normalize((string) $quantity)
                    ),
                    '0.0000'
                );

            if (Decimal::compare($received, '0.0000') > 0) {
                $anyReceived = true;
            }

            if (Decimal::compare($received, Decimal::normalize((string) $ordered->quantity)) !== 0) {
                $allReceived = false;
            }
        }

        $purchaseOrder->status = $allReceived && $orderedItems->isNotEmpty()
            ? 'received'
            : ($anyReceived ? 'partially_received' : $purchaseOrder->status);

        $purchaseOrder->save();
    }

    private function roundedProduct(string $a, string $b): string
    {
        $value = DB::query()
            ->selectRaw('ROUND(? * ?, 4) AS value', [$a, $b])
            ->value('value');

        return Decimal::normalize((string) $value);
    }

    private function weightedAverage(
        string $oldQuantity,
        string $oldAverageCost,
        string $receivedQuantity,
        string $receivedUnitCost,
        string $newQuantity
    ): string {
        $oldValue = $this->roundedProduct($oldQuantity, $oldAverageCost);
        $receivedValue = $this->roundedProduct($receivedQuantity, $receivedUnitCost);
        $totalValue = Decimal::add($oldValue, $receivedValue);

        $value = DB::query()
            ->selectRaw('ROUND(? / NULLIF(?, 0), 4) AS value', [$totalValue, $newQuantity])
            ->value('value');

        return Decimal::normalize((string) $value);
    }
}
