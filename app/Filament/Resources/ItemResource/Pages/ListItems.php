<?php
namespace App\Filament\Resources\ItemResource\Pages;
use App\Filament\Resources\ItemResource;
use Filament\Resources\Pages\ListRecords;
class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()->visible(fn (): bool => ItemResource::canCreate())]; }
}
