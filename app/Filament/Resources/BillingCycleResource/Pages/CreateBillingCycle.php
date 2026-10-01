<?php
namespace App\Filament\Resources\BillingCycleResource\Pages;
use App\Filament\Resources\BillingCycleResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateBillingCycle extends CreateRecord {
 protected static string $resource = BillingCycleResource::class;
 protected function mutateFormDataBeforeCreate(array $data): array { $data['station_id']=app(StationContext::class)->currentId(); return $data; }
}