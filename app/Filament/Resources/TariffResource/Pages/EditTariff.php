<?php

namespace App\Filament\Resources\TariffResource\Pages;

use App\Filament\Resources\TariffResource;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;

class EditTariff extends EditRecord
{
    protected static string $resource = TariffResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['station_id']);

        $stationId = app(StationContext::class)->currentId();

        abort_unless($stationId && (int) $this->record->station_id === $stationId, 403);

        return $data;
    }
}
