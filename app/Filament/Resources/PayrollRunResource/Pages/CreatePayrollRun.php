<?php
namespace App\Filament\Resources\PayrollRunResource\Pages;
use App\Filament\Resources\PayrollRunResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreatePayrollRun extends CreateRecord {
 protected static string $resource=PayrollRunResource::class;
 protected function mutateFormDataBeforeCreate(array $data): array { $data['station_id']=(int)app(StationContext::class)->currentId(); $data['created_by']=auth()->id(); return $data; }
}