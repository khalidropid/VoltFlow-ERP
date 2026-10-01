<?php
namespace App\Filament\Resources\FeederResource\Pages;
use App\Filament\Resources\FeederResource;
use Filament\Resources\Pages\ListRecords;
class ListFeeders extends ListRecords
{
    protected static string $resource = FeederResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()->visible(fn (): bool => FeederResource::canCreate())]; }
}
