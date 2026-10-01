<?php
namespace App\Filament\Resources\MaintenancePlanResource\Pages;
use App\Filament\Resources\MaintenancePlanResource;
use App\Models\Asset;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateMaintenancePlan extends CreateRecord
{
    protected static string $resource = MaintenancePlanResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array {
        $stationId=(int)app(StationContext::class)->currentId();
        abort_unless($stationId,403,'No station is selected.');
        Asset::query()->whereKey($data['asset_id'] ?? 0)->where('station_id',$stationId)->firstOrFail();
        $data['station_id']=$stationId;
        return $data;
    }
}
