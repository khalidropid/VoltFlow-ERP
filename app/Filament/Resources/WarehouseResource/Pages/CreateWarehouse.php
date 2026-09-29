<?php
namespace App\Filament\Resources\WarehouseResource\Pages;
use App\Filament\Resources\WarehouseResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array { $data['station_id']=app(StationContext::class)->currentId(); return $data; }
}
