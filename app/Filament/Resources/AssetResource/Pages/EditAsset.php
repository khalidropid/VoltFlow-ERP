<?php
namespace App\Filament\Resources\AssetResource\Pages;
use App\Filament\Resources\AssetResource;
use App\Models\AssetCategory;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;
class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;
    protected function mutateFormDataBeforeSave(array $data): array {
        unset($data['station_id']);
        $stationId=(int)app(StationContext::class)->currentId();
        abort_unless($stationId && (int)$this->record->station_id===$stationId,403);
        if(!empty($data['asset_category_id'])) AssetCategory::query()->whereKey($data['asset_category_id'])->where(function($q)use($stationId){$q->where('station_id',$stationId)->orWhereNull('station_id');})->firstOrFail();
        return $data;
    }
}
