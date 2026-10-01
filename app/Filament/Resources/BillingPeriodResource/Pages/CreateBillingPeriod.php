<?php
namespace App\Filament\Resources\BillingPeriodResource\Pages;
use App\Filament\Resources\BillingPeriodResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateBillingPeriod extends CreateRecord {
 protected static string $resource = BillingPeriodResource::class;
 protected function mutateFormDataBeforeCreate(array $data): array { $data['station_id']=app(StationContext::class)->currentId(); return $data; }
}