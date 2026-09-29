<?php
namespace App\Filament\Resources\ItemResource\Pages;
use App\Filament\Resources\ItemResource;
use App\Models\ItemCategory;
use App\Models\Station;
use App\Models\UnitOfMeasure;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateItem extends CreateRecord
{
    protected static string $resource = ItemResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array {
        $stationId=(int)app(StationContext::class)->currentId();
        abort_unless($stationId,403,'No station is selected.');
        if(!empty($data['item_category_id'])) ItemCategory::query()->whereKey($data['item_category_id'])->where('station_id',$stationId)->firstOrFail();
        UnitOfMeasure::query()->whereKey($data['unit_of_measure_id'])->firstOrFail();
        $data['station_id']=$stationId;
        return $data;
    }
}
