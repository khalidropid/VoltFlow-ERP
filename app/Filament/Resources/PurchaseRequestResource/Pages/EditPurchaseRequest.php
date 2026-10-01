<?php
namespace App\Filament\Resources\PurchaseRequestResource\Pages;
use App\Filament\Resources\PurchaseRequestResource;
use App\Models\Item;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;
class EditPurchaseRequest extends EditRecord
{
    protected static string $resource = PurchaseRequestResource::class;
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['station_id'], $data['requested_by'], $data['transaction_uuid']);
        $stationId = (int) app(StationContext::class)->currentId();
        abort_unless($stationId && (int) $this->record->station_id === $stationId, 403);
        foreach (($data['items'] ?? []) as $item) {
            Item::query()->whereKey($item['item_id'] ?? 0)->where('station_id', $stationId)->firstOrFail();
        }
        return $data;
    }
}
