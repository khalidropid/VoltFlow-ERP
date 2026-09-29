<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['station_id']);

        $stationId = app(StationContext::class)->currentId();

        abort_unless($stationId && (int) $this->record->station_id === $stationId, 403);

        return $data;
    }
}
