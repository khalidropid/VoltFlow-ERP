<?php
namespace App\Filament\Resources\FuelTankResource\Pages;
use App\Filament\Resources\FuelTankResource;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;
class EditFuelTank extends EditRecord
{
    protected static string $resource = FuelTankResource::class;
    protected function mutateFormDataBeforeSave(array $data): array {
        unset($data['station_id']);
        abort_unless((int)$this->record->station_id === (int)app(StationContext::class)->currentId(), 403);
        return $data;
    }
}
