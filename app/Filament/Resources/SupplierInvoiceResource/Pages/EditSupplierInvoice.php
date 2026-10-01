<?php

namespace App\Filament\Resources\SupplierInvoiceResource\Pages;

use App\Filament\Resources\SupplierInvoiceResource;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Supplier;
use App\Support\StationContext;
use Filament\Resources\Pages\EditRecord;

class EditSupplierInvoice extends EditRecord
{
    protected static string $resource = SupplierInvoiceResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();
        abort_unless($this->record->status === 'draft',403);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $stationId=(int)app(StationContext::class)->currentId();
        abort_unless($stationId && (int)$this->record->station_id === $stationId,403);

        Supplier::query()->whereKey($data['supplier_id'] ?? 0)
            ->where('station_id',$stationId)->where('status','active')->firstOrFail();

        if (!empty($data['goods_receipt_id'])) {
            GoodsReceipt::query()->whereKey($data['goods_receipt_id'])
                ->where('station_id',$stationId)->where('status','posted')->firstOrFail();
        }

        foreach (($data['items'] ?? []) as $line) {
            Item::query()->whereKey($line['item_id'] ?? 0)
                ->where('station_id',$stationId)->where('is_active',true)->firstOrFail();
        }

        $data['station_id']=$stationId;
        $data['transaction_uuid']=$this->record->transaction_uuid;
        $data['status']='draft';
        $data['paid_amount']=$this->record->paid_amount;
        $data['journal_entry_id']=$this->record->journal_entry_id;

        return $data;
    }
}
