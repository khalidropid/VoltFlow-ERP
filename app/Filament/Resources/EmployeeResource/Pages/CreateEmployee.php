<?php
namespace App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateEmployee extends CreateRecord {
 protected static string $resource=EmployeeResource::class;
 protected function mutateFormDataBeforeCreate(array $data): array { $data['station_id']=(int)app(StationContext::class)->currentId(); return $data; }
}