<?php
namespace App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['station_id'] = app(StationContext::class)->currentId();
        return $data;
    }
}
