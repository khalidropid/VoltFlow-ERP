<?php
namespace App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Item;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $stationId=(int)app(StationContext::class)->currentId();
        abort_unless($stationId,403,'No station is selected.');
        Supplier::query()->whereKey($data['supplier_id']??0)->where('station_id',$stationId)->firstOrFail();
        if(!empty($data['purchase_request_id'])) PurchaseRequest::query()->whereKey($data['purchase_request_id'])->where('station_id',$stationId)->firstOrFail();
        foreach(($data['items']??[]) as $item) Item::query()->whereKey($item['item_id']??0)->where('station_id',$stationId)->firstOrFail();
        $data['station_id']=$stationId;
        $data['transaction_uuid']=(string)Str::uuid();
        if(($data['status']??'draft')!=='draft') $data['status']='draft';
        return $data;
    }
}
