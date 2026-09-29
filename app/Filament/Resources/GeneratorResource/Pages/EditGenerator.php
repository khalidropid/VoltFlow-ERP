<?php

namespace App\Filament\Resources\GeneratorResource\Pages;

use App\Filament\Resources\GeneratorResource;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;

class EditGenerator extends EditRecord
{
    protected static string $resource = GeneratorResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['station_id']);
        abort_unless((int) $this->record->station_id === (int) app(StationContext::class)->currentId(), 403);

        return $data;
    }
}
