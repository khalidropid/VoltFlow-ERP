<?php

namespace App\Filament\Resources\SupplierInvoiceResource\Pages;

use App\Filament\Resources\SupplierInvoiceResource;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Supplier;
use App\Support\StationContext;
use Illuminate\Support\Str;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplierInvoice extends CreateRecord
{
    protected static string $resource = SupplierInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $stationId = (int) app(StationContext::class)->currentId();
        abort_unless($stationId,403,'No station is selected.');

        Supplier::query()->whereKey($data['supplier_id'] ?? 0)
            ->where('station_id',$stationId)->where('status','active')->firstOrFail();

        if (! empty($data['goods_receipt_id'])) {
            GoodsReceipt::query()->whereKey($data['goods_receipt_id'])
                ->where('station_id',$stationId)->where('status','posted')->firstOrFail();
        }

        foreach (($data['items'] ?? []) as $line) {
            Item::query()->whereKey($line['item_id'] ?? 0)
                ->where('station_id',$stationId)->where('is_active',true)->firstOrFail();
        }

        $data['station_id']=$stationId;
        $data['transaction_uuid']=(string)Str::uuid();
        $data['status']='draft';
        $data['paid_amount']='0.0000';
        $data['journal_entry_id']=null;

        return $data;
    }
}
