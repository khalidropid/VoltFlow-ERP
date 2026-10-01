<?php
namespace App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\ListRecords;
class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()->visible(fn (): bool => PurchaseOrderResource::canCreate())]; }
}
