<?php
namespace App\Filament\Resources\FuelTypeResource\Pages;
use App\Filament\Resources\FuelTypeResource;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;
class EditFuelType extends EditRecord
{
    protected static string $resource = FuelTypeResource::class;
    protected function mutateFormDataBeforeSave(array $data): array {
        unset($data['station_id']);
        abort_unless((int)$this->record->station_id === (int)app(StationContext::class)->currentId() || $this->record->station_id === null, 403);
        return $data;
    }
}
