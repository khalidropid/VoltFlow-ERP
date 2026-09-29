<?php

namespace App\Filament\Resources\GeneratorResource\Pages;

use App\Filament\Resources\GeneratorResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;

class CreateGenerator extends CreateRecord
{
    protected static string $resource = GeneratorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['station_id'] = app(StationContext::class)->currentId();

        return $data;
    }
}
