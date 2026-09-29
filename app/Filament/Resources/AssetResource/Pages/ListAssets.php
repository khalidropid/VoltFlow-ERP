<?php
namespace App\Filament\Resources\AssetResource\Pages;
use App\Filament\Resources\AssetResource;
use Filament\Resources\Pages\ListRecords;
class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()->visible(fn (): bool => AssetResource::canCreate())]; }
}
