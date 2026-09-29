<?php

namespace App\Filament\Resources\GoodsReceiptResource\Pages;

use App\Filament\Resources\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Warehouse;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;

class EditGoodsReceipt extends EditRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();
        abort_unless($this->record->status === 'draft', 403);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $stationId = (int) app(StationContext::class)->currentId();
        abort_unless($stationId && (int) $this->record->station_id === $stationId, 403);

        Warehouse::query()->whereKey($data['warehouse_id'] ?? 0)
            ->where('station_id', $stationId)->where('is_active', true)->firstOrFail();

        foreach (($data['items'] ?? []) as $line) {
            Item::query()->whereKey($line['item_id'] ?? 0)
                ->where('station_id', $stationId)->where('is_active', true)->firstOrFail();
        }

        $data['station_id'] = $stationId;
        $data['transaction_uuid'] = $this->record->transaction_uuid;
        $data['status'] = 'draft';

        return $data;
    }
}
