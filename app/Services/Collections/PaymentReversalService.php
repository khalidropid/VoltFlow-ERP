<?php

namespace App\Services\Collections;

use App\Models\CollectorAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class PaymentReversalService
{
    public function void(int $paymentId, int $stationId, int $actorId, string $voidedAt, string $reason): Payment
    {
        return DB::transaction(function () use ($paymentId, $stationId, $actorId, $voidedAt, $reason) {
            $payment = Payment::query()->whereKey($paymentId)->where('station_id', $stationId)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'voided') {
                return $payment->load(['journalEntry', 'reversalJournalEntry']);
            }
            if ($payment->status !== 'posted') {
                throw new CollectionException('Only a posted payment can be voided.');
            }
            if (!$payment->journal_entry_id) {
                throw new CollectionException('The payment has no posted journal entry and cannot be voided safely.');
            }

            $collector = CollectorAccount::query()
                ->where('station_id', $stationId)->where('collector_id', $payment->collector_id)
                ->where('status', 'open')->lockForUpdate()->firstOrFail();

            $amount = Decimal::normalize((string) $payment->amount);
            if (Decimal::compare((string) $collector->balance, $amount) < 0) {
                throw new CollectionException('The payment cannot be voided because the amount is no longer available in the collector custody balance.');
            }

            $invoice = null;
            if ($payment->invoice_id) {
                $invoice = Invoice::query()->whereKey($payment->invoice_id)->where('station_id', $stationId)->lockForUpdate()->firstOrFail();
                $paid = Decimal::sub((string) $invoice->paid_amount, $amount);
                if (Decimal::compare($paid, '0') < 0) {
                    throw new CollectionException('Voiding this payment would make the invoice paid amount negative.');
                }
                $invoice->paid_amount = $paid;
                $invoice->status = Decimal::compare($paid, '0') === 0
                    ? 'issued'
                    : (Decimal::compare($paid, (string) $invoice->total) < 0 ? 'partially_paid' : 'paid');
            }

            $accounting = app(\App\Services\Accounting\CollectionAccountingService::class);
            $period = $accounting->openPeriodForDate($stationId, $voidedAt);

            $entry = app(\App\Services\Accounting\JournalEntryService::class)->createAndPost(
                $stationId, $period->id, 'REV-PAY-' . $payment->receipt_number,
                date('Y-m-d', strtotime($voidedAt)),
                'Reversal of payment ' . $payment->receipt_number,
                [
                    ['account_id' => $collector->account_id, 'debit' => '0', 'credit' => $amount],
                    ['account_id' => $accounting->postableAccountId($stationId, '1200'), 'debit' => $amount, 'credit' => '0'],
                ],
                \App\Models\User::find($actorId),
                'payment_reversal',
                $payment->id
            );

            $entry->reversal_of_journal_entry_id = $payment->journal_entry_id;
            $entry->save();

            $collector->balance = Decimal::sub((string) $collector->balance, $amount);
            $collector->save();

            if ($invoice) {
                $invoice->save();
            }

            $payment->status = 'voided';
            $payment->voided_by = $actorId;
            $payment->voided_at = $voidedAt;
            $payment->void_reason = $reason;
            $payment->reversal_journal_entry_id = $entry->id;
            $payment->save();

            return $payment->fresh(['journalEntry', 'reversalJournalEntry']);
        }, 3);
    }
}
