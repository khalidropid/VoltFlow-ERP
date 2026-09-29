<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $stationId = app(StationContext::class)->currentId();

        abort_unless($stationId, 403, 'No station is selected.');

        $data['station_id'] = $stationId;

        return $data;
    }
}
