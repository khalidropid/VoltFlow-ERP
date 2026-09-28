<?php

namespace App\Services\Collections;

use App\Models\CollectorAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentService
{
    public function collect(
        int $stationId, int $collectorId, int $customerId, ?int $invoiceId,
        int $cashAccountId, string $transactionUuid, string $receiptNumber,
        string $paidAt, string $amount, string $method = 'cash', ?string $notes = null
    ): Payment {
        return DB::transaction(function () use (
            $stationId, $collectorId, $customerId, $invoiceId, $cashAccountId,
            $transactionUuid, $receiptNumber, $paidAt, $amount, $method, $notes
        ) {
            $existing = Payment::query()->where('transaction_uuid', $transactionUuid)->first();
            if ($existing) {
                if ($existing->station_id !== $stationId || Decimal::normalize((string) $existing->amount) !== Decimal::normalize($amount)) {
                    throw new CollectionException('The transaction UUID is already associated with a different payment.');
                }
                return $existing;
            }

            $collector = CollectorAccount::query()
                ->where('station_id', $stationId)
                ->where('collector_id', $collectorId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (!$collector) throw new CollectionException('The collector does not have an open collection account.');

            $amount = Decimal::normalize($amount);
            if (Decimal::compare($amount, '0') <= 0) throw new CollectionException('Payment amount must be greater than zero.');

            if ($invoiceId) {
                $invoice = Invoice::query()->whereKey($invoiceId)->where('station_id', $stationId)->lockForUpdate()->firstOrFail();
                if ($invoice->customer_id !== $customerId || in_array($invoice->status, ['void', 'paid'], true)) {
                    throw new CollectionException('The invoice cannot receive this payment.');
                }

                $remaining = Decimal::sub((string) $invoice->total, (string) $invoice->paid_amount);
                if (Decimal::compare($amount, $remaining) > 0) {
                    throw new CollectionException('Payment exceeds the invoice outstanding balance.');
                }

                $invoice->paid_amount = Decimal::add((string) $invoice->paid_amount, $amount);
                $invoice->status = Decimal::compare((string) $invoice->paid_amount, (string) $invoice->total) === 0
                    ? 'paid' : 'partially_paid';
                $invoice->save();
            }

            $payment = Payment::create([
                'transaction_uuid' => $transactionUuid,
                'station_id' => $stationId,
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'collector_id' => $collectorId,
                'cash_account_id' => $cashAccountId,
                'receipt_number' => $receiptNumber,
                'paid_at' => $paidAt,
                'amount' => $amount,
                'method' => $method,
                'status' => 'posted',
                'notes' => $notes,
            ]);

            $collector->balance = Decimal::add((string) $collector->balance, $amount);
            $collector->save();

            return $payment;
        }, 3);
    }
}
