<?php
namespace App\Filament\Resources\MeterInstallationResource\Pages;
use App\Filament\Resources\MeterInstallationResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateMeterInstallation extends CreateRecord {
 protected static string $resource = MeterInstallationResource::class;
 protected function mutateFormDataBeforeCreate(array $data): array { $data['station_id']=app(StationContext::class)->currentId(); return $data; }
}