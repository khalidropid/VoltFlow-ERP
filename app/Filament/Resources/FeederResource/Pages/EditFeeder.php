<?php
namespace App\Filament\Resources\FeederResource\Pages;
use App\Filament\Resources\FeederResource;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;
class EditFeeder extends EditRecord
{
    protected static string $resource = FeederResource::class;
    protected function mutateFormDataBeforeSave(array $data): array {
        unset($data['station_id']);
        abort_unless((int)$this->record->station_id === (int)app(StationContext::class)->currentId(), 403);
        return $data;
    }
}
