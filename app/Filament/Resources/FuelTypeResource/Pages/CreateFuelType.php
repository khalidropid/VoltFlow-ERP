<?php
namespace App\Filament\Resources\FuelTypeResource\Pages;
use App\Filament\Resources\FuelTypeResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateFuelType extends CreateRecord
{
    protected static string $resource = FuelTypeResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array { $data['station_id']=app(StationContext::class)->currentId(); return $data; }
}
