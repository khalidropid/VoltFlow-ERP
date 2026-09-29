<?php
namespace App\Filament\Resources\MaintenancePlanResource\Pages;
use App\Filament\Resources\MaintenancePlanResource;
use Filament\Resources\Pages\ListRecords;
class ListMaintenancePlans extends ListRecords
{
    protected static string $resource = MaintenancePlanResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()->visible(fn (): bool => MaintenancePlanResource::canCreate())]; }
}
