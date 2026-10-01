<?php
namespace App\Filament\Resources\PayrollPeriodResource\Pages;
use App\Filament\Resources\PayrollPeriodResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreatePayrollPeriod extends CreateRecord {
 protected static string $resource=PayrollPeriodResource::class;
 protected function mutateFormDataBeforeCreate(array $data): array { $data['station_id']=(int)app(StationContext::class)->currentId(); return $data; }
}