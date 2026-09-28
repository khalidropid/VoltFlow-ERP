<?php

namespace App\Services\Accounting;

use App\Models\CashAccount;
use App\Models\ChartOfAccount;
use App\Models\CollectionSettlement;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\CollectorAccount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CollectionAccountingService
{
    public function postInvoice(Invoice $invoice, ?int $actorId = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actorId) {
            $invoice->refresh();
            if ($invoice->journal_entry_id) return $invoice;
            $ar = $this->account($invoice->station_id, '1200');
            $revenue = $this->account($invoice->station_id, '4100');
            $period = $this->period($invoice->station_id, $invoice->invoice_date->format('Y-m-d'));

            $entry = app(JournalEntryService::class)->createAndPost(
                $invoice->station_id, $period->id, 'INV-' . $invoice->number,
                $invoice->invoice_date->format('Y-m-d'), 'Invoice ' . $invoice->number,
                [
                    ['account_id' => $ar->id, 'debit' => (string) $invoice->total, 'credit' => '0'],
                    ['account_id' => $revenue->id, 'debit' => '0', 'credit' => (string) $invoice->total],
                ],
                $actorId ? \App\Models\User::find($actorId) : null, 'invoice', $invoice->id
            );

            $invoice->journal_entry_id = $entry->id;
            $invoice->save();
            return $invoice;
        });
    }

    public function postPayment(Payment $payment, ?int $actorId = null): Payment
    {
        return DB::transaction(function () use ($payment, $actorId) {
            $payment->refresh();
            if ($payment->journal_entry_id) return $payment;

            $collectorAccount = CollectorAccount::query()->where('station_id', $payment->station_id)
                ->where('collector_id', $payment->collector_id)->firstOrFail();
            if (!$collectorAccount->account_id) throw new RuntimeException('Collector GL account is not configured.');

            $ar = $this->account($payment->station_id, '1200');
            $period = $this->period($payment->station_id, $payment->paid_at->format('Y-m-d'));

            $entry = app(JournalEntryService::class)->createAndPost(
                $payment->station_id, $period->id, 'PAY-' . $payment->receipt_number,
                $payment->paid_at->format('Y-m-d'), 'Payment ' . $payment->receipt_number,
                [
                    ['account_id' => $collectorAccount->account_id, 'debit' => (string) $payment->amount, 'credit' => '0'],
                    ['account_id' => $ar->id, 'debit' => '0', 'credit' => (string) $payment->amount],
                ],
                $actorId ? \App\Models\User::find($actorId) : null, 'payment', $payment->id
            );

            $payment->journal_entry_id = $entry->id;
            $payment->save();
            return $payment;
        });
    }

    public function postSettlement(CollectionSettlement $settlement, ?int $actorId = null): CollectionSettlement
    {
        return DB::transaction(function () use ($settlement, $actorId) {
            $collector = CollectorAccount::query()->whereKey($settlement->collector_account_id)->firstOrFail();
            $cash = CashAccount::query()->whereKey($settlement->cash_account_id)->firstOrFail();
            if (!$collector->account_id || !$cash->account_id) throw new RuntimeException('Settlement GL accounts are not configured.');

            $period = $this->period($settlement->station_id, $settlement->settled_at->format('Y-m-d'));
            app(JournalEntryService::class)->createAndPost(
                $settlement->station_id, $period->id, 'SET-' . $settlement->number,
                $settlement->settled_at->format('Y-m-d'), 'Collection settlement ' . $settlement->number,
                [
                    ['account_id' => $cash->account_id, 'debit' => (string) $settlement->amount, 'credit' => '0'],
                    ['account_id' => $collector->account_id, 'debit' => '0', 'credit' => (string) $settlement->amount],
                ],
                $actorId ? \App\Models\User::find($actorId) : null, 'collection_settlement', $settlement->id
            );
            return $settlement;
        });
    }

    private function account(int $stationId, string $code): ChartOfAccount
    {
        return ChartOfAccount::query()->where('station_id', $stationId)->where('code', $code)
            ->where('is_postable', true)->where('is_active', true)->firstOrFail();
    }

    private function period(int $stationId, string $date): FiscalPeriod
    {
        return FiscalPeriod::query()->where('station_id', $stationId)->where('status', 'open')
            ->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->firstOrFail();
    }
}
