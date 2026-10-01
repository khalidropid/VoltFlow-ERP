<?php
namespace App\Filament\Resources\MaintenancePlanResource\Pages;
use App\Filament\Resources\MaintenancePlanResource;
use App\Models\Asset;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;
class EditMaintenancePlan extends EditRecord
{
    protected static string $resource = MaintenancePlanResource::class;
    protected function mutateFormDataBeforeSave(array $data): array {
        unset($data['station_id']);
        $stationId=(int)app(StationContext::class)->currentId();
        abort_unless($stationId && (int)$this->record->station_id===$stationId,403);
        if(isset($data['asset_id'])) Asset::query()->whereKey($data['asset_id'])->where('station_id',$stationId)->firstOrFail();
        return $data;
    }
}
