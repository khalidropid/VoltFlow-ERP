<?php

namespace App\Filament\Resources\SupplierPaymentResource\Pages;

use App\Filament\Resources\SupplierPaymentResource;
use App\Services\Procurement\SupplierPaymentService;
use App\Support\StationContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateSupplierPayment extends CreateRecord
{
    protected static string $resource = SupplierPaymentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $stationId=(int)app(StationContext::class)->currentId();
        abort_unless($stationId,403,'No station is selected.');
        $payment=app(SupplierPaymentService::class)->pay(
            $stationId,
            (int)$data['supplier_id'],
            (int)$data['cash_account_id'],
            (string)Str::uuid(),
            (string)$data['receipt_number'],
            \Illuminate\Carbon\Carbon::parse((string)$data['paid_at'])->format('Y-m-d H:i:s'),
            (string)$data['amount'],
            $data['allocations'] ?? [],
            (string)$data['method'],
            auth()->user(),
        );
        return $payment;
    }
}
