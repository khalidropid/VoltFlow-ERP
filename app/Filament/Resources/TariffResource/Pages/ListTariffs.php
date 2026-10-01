<?php

namespace App\Filament\Resources\TariffResource\Pages;

use App\Filament\Resources\TariffResource;
use Filament\Resources\Pages\ListRecords;

class ListTariffs extends ListRecords
{
    protected static string $resource = TariffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()->visible(fn (): bool => TariffResource::canCreate()),
        ];
    }
}
