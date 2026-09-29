<?php
namespace App\Filament\Resources\ItemResource\Pages;
use App\Filament\Resources\ItemResource;
use App\Models\ItemCategory;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;
class EditItem extends EditRecord
{
    protected static string $resource = ItemResource::class;
    protected function mutateFormDataBeforeSave(array $data): array {
        unset($data['station_id']);
        $stationId=(int)app(StationContext::class)->currentId();
        abort_unless($stationId && (int)$this->record->station_id===$stationId,403);
        if(!empty($data['item_category_id'])) ItemCategory::query()->whereKey($data['item_category_id'])->where('station_id',$stationId)->firstOrFail();
        return $data;
    }
}
