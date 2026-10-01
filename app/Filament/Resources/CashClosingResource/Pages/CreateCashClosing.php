<?php
namespace App\Filament\Resources\CashClosingResource\Pages;
use App\Filament\Resources\CashClosingResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateCashClosing extends CreateRecord{protected static string $resource=CashClosingResource::class;protected function mutateFormDataBeforeCreate(array $data): array{$data['station_id']=app(StationContext::class)->currentId();return $data;}}