<?php
namespace App\Filament\Resources\CustomerTariffResource\Pages;
use App\Filament\Resources\CustomerTariffResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateCustomerTariff extends CreateRecord {
 protected static string $resource = CustomerTariffResource::class;
 protected function mutateFormDataBeforeCreate(array $data): array { $data['station_id']=app(StationContext::class)->currentId(); return $data; }
}