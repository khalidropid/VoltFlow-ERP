<?php
namespace App\Filament\Resources\FuelTankResource\Pages;
use App\Filament\Resources\FuelTankResource;
use Filament\Resources\Pages\ListRecords;
class ListFuelTanks extends ListRecords
{
    protected static string $resource = FuelTankResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()->visible(fn (): bool => FuelTankResource::canCreate())]; }
}
