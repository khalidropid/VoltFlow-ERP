<?php

namespace App\Filament\Resources\TariffResource\Pages;

use App\Filament\Resources\TariffResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;

class CreateTariff extends CreateRecord
{
    protected static string $resource = TariffResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $stationId = app(StationContext::class)->currentId();

        abort_unless($stationId, 403, 'No station is selected.');

        $data['station_id'] = $stationId;

        return $data;
    }
}
