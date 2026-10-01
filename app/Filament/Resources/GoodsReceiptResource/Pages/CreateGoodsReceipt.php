<?php

namespace App\Filament\Resources\GoodsReceiptResource\Pages;

use App\Filament\Resources\GoodsReceiptResource;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateGoodsReceipt extends CreateRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $stationId = (int) app(StationContext::class)->currentId();
        abort_unless($stationId, 403, 'No station is selected.');

        Warehouse::query()->whereKey($data['warehouse_id'] ?? 0)
            ->where('station_id', $stationId)->where('is_active', true)->firstOrFail();

        if (! empty($data['purchase_order_id'])) {
            PurchaseOrder::query()->whereKey($data['purchase_order_id'])
                ->where('station_id', $stationId)
                ->whereIn('status', ['approved','sent','partially_received'])
                ->firstOrFail();
        }

        foreach (($data['items'] ?? []) as $line) {
            Item::query()->whereKey($line['item_id'] ?? 0)
                ->where('station_id', $stationId)->where('is_active', true)->firstOrFail();
        }

        $data['station_id'] = $stationId;
        $data['transaction_uuid'] = (string) Str::uuid();
        $data['status'] = 'draft';

        return $data;
    }
}
