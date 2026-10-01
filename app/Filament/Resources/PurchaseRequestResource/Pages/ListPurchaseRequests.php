<?php
namespace App\Filament\Resources\PurchaseRequestResource\Pages;
use App\Filament\Resources\PurchaseRequestResource;
use Filament\Resources\Pages\ListRecords;
class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestResource::class;
    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->visible(fn (): bool => PurchaseRequestResource::canCreate())];
    }
}
