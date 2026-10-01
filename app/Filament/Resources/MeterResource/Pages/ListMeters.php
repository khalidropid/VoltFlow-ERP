<?php

namespace App\Filament\Resources\MeterResource\Pages;

use App\Filament\Resources\MeterResource;
use Filament\Resources\Pages\ListRecords;

class ListMeters extends ListRecords
{
    protected static string $resource = MeterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()->visible(fn (): bool => MeterResource::canCreate()),
        ];
    }
}
