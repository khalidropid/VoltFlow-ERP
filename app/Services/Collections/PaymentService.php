<?php

namespace App\Services\Collections;

use App\Models\CashAccount;
use App\Models\CollectorAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function collect(
        int $stationId, int $collectorId, int $customerId, ?int $invoiceId,
        int $cashAccountId, string $transactionUuid, string $receiptNumber,
        string $paidAt, string $amount, string $method = 'cash', ?string $notes = null
    ): Payment {
        return DB::transaction(function () use ($stationId, $collectorId, $customerId, $invoiceId, $cashAccountId, $transactionUuid, $receiptNumber, $paidAt, $amount, $method, $notes) {
            $existing = Payment::query()->where('transaction_uuid', $transactionUuid)->first();
            if ($existing) {
                $this->assertSameIdentity($existing, $stationId, $collectorId, $customerId, $invoiceId, $cashAccountId, $amount, $receiptNumber, $paidAt, $method);

                return $existing;
            }

            $collector = CollectorAccount::query()->where('station_id', $stationId)->where('collector_id', $collectorId)
                ->where('status', 'open')->lockForUpdate()->first();
            if (!$collector) throw new CollectionException('The collector does not have an open collection account.');

            $existing = Payment::query()->where('transaction_uuid', $transactionUuid)->first();
            if ($existing) {
                $this->assertSameIdentity($existing, $stationId, $collectorId, $customerId, $invoiceId, $cashAccountId, $amount, $receiptNumber, $paidAt, $method);

                return $existing;
            }

            $cash = CashAccount::query()->whereKey($cashAccountId)->where('station_id', $stationId)
                ->where('is_active', true)->first();
            if (!$cash) throw new CollectionException('The cash/bank account is not active for this station.');

            $amount = Decimal::normalize($amount);
            if (Decimal::compare($amount, '0') <= 0) throw new CollectionException('Payment amount must be greater than zero.');

            if ($invoiceId) {
                $invoice = Invoice::query()->whereKey($invoiceId)->where('station_id', $stationId)->lockForUpdate()->firstOrFail();
                if ($invoice->customer_id !== $customerId || in_array($invoice->status, ['void', 'paid'], true)) {
                    throw new CollectionException('The invoice cannot receive this payment.');
                }

                $remaining = Decimal::sub((string) $invoice->total, (string) $invoice->paid_amount);
                if (Decimal::compare($amount, $remaining) > 0) throw new CollectionException('Payment exceeds the invoice outstanding balance.');

                $invoice->paid_amount = Decimal::add((string) $invoice->paid_amount, $amount);
                $invoice->status = Decimal::compare((string) $invoice->paid_amount, (string) $invoice->total) === 0 ? 'paid' : 'partially_paid';
                $invoice->save();
            }

            $payment = Payment::create([
                'transaction_uuid' => $transactionUuid, 'station_id' => $stationId, 'customer_id' => $customerId,
                'invoice_id' => $invoiceId, 'collector_id' => $collectorId, 'cash_account_id' => $cashAccountId,
                'receipt_number' => $receiptNumber, 'paid_at' => $paidAt, 'amount' => $amount,
                'method' => $method, 'status' => 'posted', 'notes' => $notes,
            ]);

            $collector->balance = Decimal::add((string) $collector->balance, $amount);
            $collector->save();

            return app(\App\Services\Accounting\CollectionAccountingService::class)->postPayment($payment, $collectorId);
        }, 3);
    }


    public function allocate(int $stationId, int $paymentId, int $invoiceId, string $amount): PaymentAllocation
    {
        return DB::transaction(function () use ($stationId, $paymentId, $invoiceId, $amount) {
            $payment = Payment::query()->whereKey($paymentId)->where('station_id', $stationId)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::query()->whereKey($invoiceId)->where('station_id', $stationId)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'posted' || $invoice->status === 'void') throw new CollectionException('Payment or invoice is not allocatable.');
            if ($payment->customer_id !== $invoice->customer_id) throw new CollectionException('Payment and invoice customer mismatch.');
            $value = Decimal::normalize($amount);
            if (Decimal::compare($value, '0') <= 0) throw new CollectionException('Allocation must be positive.');
            $allocated = Decimal::normalize((string) PaymentAllocation::query()->where('payment_id', $payment->id)->sum('amount'));
            if (Decimal::compare(Decimal::add($allocated, $value), (string) $payment->amount) > 0) throw new CollectionException('Allocation exceeds payment.');
            $invoiceAllocated = Decimal::normalize((string) PaymentAllocation::query()->where('invoice_id', $invoice->id)->sum('amount'));
            $remaining = Decimal::sub((string) $invoice->total, $invoiceAllocated);
            if (Decimal::compare($value, $remaining) > 0) throw new CollectionException('Allocation exceeds invoice outstanding balance.');
            $allocation = PaymentAllocation::create(['payment_id'=>$payment->id,'invoice_id'=>$invoice->id,'amount'=>$value]);
            $newPaid = Decimal::add((string)$invoice->paid_amount, $value);
            $invoice->update(['paid_amount'=>$newPaid,'status'=>Decimal::compare($newPaid,(string)$invoice->total)>=0?'paid':'partially_paid']);
            return $allocation;
        }, 3);
    }

    private function assertSameIdentity(
        Payment $existing,
        int $stationId,
        int $collectorId,
        int $customerId,
        ?int $invoiceId,
        int $cashAccountId,
        string $amount,
        string $receiptNumber,
        string $paidAt,
        string $method
    ): void {
        $sameIdentity =
            $existing->station_id === $stationId &&
            $existing->collector_id === $collectorId &&
            $existing->customer_id === $customerId &&
            $existing->invoice_id === $invoiceId &&
            $existing->cash_account_id === $cashAccountId &&
            Decimal::normalize((string) $existing->amount) === Decimal::normalize($amount) &&
            $existing->receipt_number === $receiptNumber &&
            $existing->paid_at?->format('Y-m-d H:i:s') === date('Y-m-d H:i:s', strtotime($paidAt)) &&
            $existing->method === $method;

        if (!$sameIdentity) {
            throw new CollectionException('The transaction UUID is already associated with a different payment.');
        }
    }
}
