<?php
namespace App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Item;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;
class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['station_id'],$data['transaction_uuid']);
        $stationId=(int)app(StationContext::class)->currentId();
        abort_unless($stationId && (int)$this->record->station_id===$stationId,403);
        Supplier::query()->whereKey($data['supplier_id']??0)->where('station_id',$stationId)->firstOrFail();
        if(!empty($data['purchase_request_id'])) PurchaseRequest::query()->whereKey($data['purchase_request_id'])->where('station_id',$stationId)->firstOrFail();
        foreach(($data['items']??[]) as $item) Item::query()->whereKey($item['item_id']??0)->where('station_id',$stationId)->firstOrFail();
        if(in_array($this->record->status,['approved','sent','partially_received','received','cancelled'],true)){
            abort(409,'Only draft purchase orders can be edited.');
        }
        return $data;
    }
}
