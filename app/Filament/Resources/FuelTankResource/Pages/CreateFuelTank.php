<?php
namespace App\Filament\Resources\FuelTankResource\Pages;
use App\Filament\Resources\FuelTankResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateFuelTank extends CreateRecord
{
    protected static string $resource = FuelTankResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array { $data['station_id']=app(StationContext::class)->currentId(); return $data; }
}
