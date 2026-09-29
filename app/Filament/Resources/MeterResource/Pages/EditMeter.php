<?php

namespace App\Filament\Resources\MeterResource\Pages;

use App\Filament\Resources\MeterResource;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;

class EditMeter extends EditRecord
{
    protected static string $resource = MeterResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['station_id']);

        $stationId = app(StationContext::class)->currentId();

        abort_unless($stationId && (int) $this->record->station_id === $stationId, 403);

        if (isset($data['customer_id'])) {
            Customer::query()
                ->whereKey($data['customer_id'])
                ->where('station_id', $stationId)
                ->firstOrFail();
        }

        return $data;
    }
}
