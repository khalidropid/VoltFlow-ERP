<?php
namespace App\Filament\Resources\BankReconciliationResource\Pages;
use App\Filament\Resources\BankReconciliationResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateBankReconciliation extends CreateRecord{protected static string $resource=BankReconciliationResource::class;protected function mutateFormDataBeforeCreate(array $data): array{$data['station_id']=app(StationContext::class)->currentId();return $data;}}