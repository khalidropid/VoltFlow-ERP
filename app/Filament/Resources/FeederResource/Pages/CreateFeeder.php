<?php
namespace App\Filament\Resources\FeederResource\Pages;
use App\Filament\Resources\FeederResource;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateFeeder extends CreateRecord
{
    protected static string $resource = FeederResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array { $data['station_id']=app(StationContext::class)->currentId(); return $data; }
}
