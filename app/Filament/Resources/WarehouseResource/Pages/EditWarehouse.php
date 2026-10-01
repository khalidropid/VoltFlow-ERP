<?php
namespace App\Filament\Resources\WarehouseResource\Pages;
use App\Filament\Resources\WarehouseResource;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;
class EditWarehouse extends EditRecord
{
    protected static string $resource = WarehouseResource::class;
    protected function mutateFormDataBeforeSave(array $data): array {
        unset($data['station_id']);
        abort_unless((int)$this->record->station_id === (int)app(StationContext::class)->currentId(),403);
        return $data;
    }
}
