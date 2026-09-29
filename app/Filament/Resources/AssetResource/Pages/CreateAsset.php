<?php
namespace App\Filament\Resources\AssetResource\Pages;
use App\Filament\Resources\AssetResource;
use App\Models\AssetCategory;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array {
        $stationId=(int)app(StationContext::class)->currentId();
        abort_unless($stationId,403,'No station is selected.');
        if(!empty($data['asset_category_id'])) AssetCategory::query()->whereKey($data['asset_category_id'])->where(function($q)use($stationId){$q->where('station_id',$stationId)->orWhereNull('station_id');})->firstOrFail();
        $data['station_id']=$stationId;
        return $data;
    }
}
