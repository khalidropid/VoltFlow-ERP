<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\Tariff;
use App\Models\CustomerTariff;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingService
{
    public function issueForReading(int $stationId, MeterReading $reading, string $invoiceUuid, string $invoiceNumber, string $invoiceDate, ?string $dueDate = null): Invoice
    {
        return DB::transaction(function () use ($stationId, $reading, $invoiceUuid, $invoiceNumber, $invoiceDate, $dueDate) {
            $reading->loadMissing('meter.customer');
            if ($reading->station_id !== $stationId || $reading->status !== 'validated') throw new RuntimeException('The reading is not valid for billing.');
            if (Invoice::query()->where('reading_id', $reading->id)->exists()) throw new RuntimeException('This reading has already been invoiced.');

            $tariff = CustomerTariff::query()
                ->where('station_id', $stationId)
                ->where('customer_id', $reading->meter->customer_id)
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $reading->reading_at)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $reading->reading_at))
                ->with(['tariff.slabs' => fn ($q) => $q->orderBy('sort_order')])
                ->orderByDesc('effective_from')
                ->first()?->tariff;

            if (!$tariff) {
                $tariff = Tariff::query()->where('station_id', $stationId)->where('is_active', true)
                    ->whereDate('effective_from', '<=', $reading->reading_at)
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $reading->reading_at))
                    ->with(['slabs' => fn ($q) => $q->orderBy('sort_order')])
                    ->orderByDesc('effective_from')
                    ->first();
            }

            if (!$tariff || $tariff->slabs->isEmpty()) throw new RuntimeException('No active tariff is available for this reading date.');

            $remaining = Decimal::normalize((string) $reading->consumption);
            $subtotal = '0.0000';
            $items = [];

            foreach ($tariff->slabs as $slab) {
                if (Decimal::compare($remaining, '0') <= 0) break;
                $capacity = $slab->to_unit === null ? $remaining : Decimal::sub((string) $slab->to_unit, (string) $slab->from_unit);
                $quantity = Decimal::compare($remaining, $capacity) < 0 ? $remaining : $capacity;
                if (Decimal::compare($quantity, '0') <= 0) continue;

                $amount = Decimal::multiply((string) $slab->rate, $quantity);
                $subtotal = Decimal::add($subtotal, $amount);
                $remaining = Decimal::sub($remaining, $quantity);
                $items[] = ['quantity' => $quantity, 'unit_rate' => $slab->rate, 'amount' => $amount, 'description' => $tariff->name, 'line_no' => count($items) + 1];
            }

            if (Decimal::compare($remaining, '0') > 0) throw new RuntimeException('The tariff slabs do not cover the full consumption.');

            $invoice = Invoice::create([
                'transaction_uuid' => $invoiceUuid, 'station_id' => $stationId,
                'customer_id' => $reading->meter->customer_id, 'meter_id' => $reading->meter_id,
                'reading_id' => $reading->id, 'tariff_id' => $tariff->id, 'number' => $invoiceNumber,
                'invoice_date' => $invoiceDate, 'due_date' => $dueDate,
                'previous_reading' => $reading->previous_reading_value, 'current_reading' => $reading->reading_value,
                'consumption' => $reading->consumption, 'subtotal' => $subtotal,
                'discount' => '0.0000', 'tax' => '0.0000', 'total' => $subtotal,
                'paid_amount' => '0.0000', 'status' => 'issued',
            ]);

            foreach ($items as $item) $invoice->items()->create($item);
            return app(\App\Services\Accounting\CollectionAccountingService::class)->postInvoice($invoice);
        }, 3);
    }
}
