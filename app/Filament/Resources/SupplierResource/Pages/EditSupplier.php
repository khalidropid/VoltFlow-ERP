<?php
namespace App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;
class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['station_id']);
        abort_unless((int) $this->record->station_id === (int) app(StationContext::class)->currentId(), 403);
        return $data;
    }
}
