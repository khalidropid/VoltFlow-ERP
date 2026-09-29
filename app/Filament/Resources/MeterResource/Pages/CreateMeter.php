<?php

namespace App\Filament\Resources\MeterResource\Pages;

use App\Filament\Resources\MeterResource;
use App\Models\Customer;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;

class CreateMeter extends CreateRecord
{
    protected static string $resource = MeterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $stationId = app(StationContext::class)->currentId();

        abort_unless($stationId, 403, 'No station is selected.');

        $customer = Customer::query()
            ->whereKey($data['customer_id'] ?? 0)
            ->where('station_id', $stationId)
            ->firstOrFail();

        $data['station_id'] = $stationId;
        $data['customer_id'] = $customer->getKey();

        return $data;
    }
}
