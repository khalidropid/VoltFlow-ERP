<?php
namespace App\Filament\Resources\PurchaseRequestResource\Pages;
use App\Filament\Resources\PurchaseRequestResource;
use App\Models\Item;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
class CreatePurchaseRequest extends CreateRecord
{
    protected static string $resource = PurchaseRequestResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $stationId = (int) app(StationContext::class)->currentId();
        abort_unless($stationId, 403, 'No station is selected.');
        foreach (($data['items'] ?? []) as $item) {
            Item::query()->whereKey($item['item_id'] ?? 0)->where('station_id', $stationId)->firstOrFail();
        }
        $data['station_id'] = $stationId;
        $data['requested_by'] = auth()->id();
        $data['transaction_uuid'] = (string) Str::uuid();
        return $data;
    }
}
