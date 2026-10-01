<?php

namespace App\Filament\Resources\GeneratorResource\Pages;

use App\Filament\Resources\GeneratorResource;
use Filament\Resources\Pages\ListRecords;

class ListGenerators extends ListRecords
{
    protected static string $resource = GeneratorResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->visible(fn (): bool => GeneratorResource::canCreate())];
    }
}
