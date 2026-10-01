<?php

namespace App\Services\Collections;

use App\Models\CashAccount;
use App\Models\CollectionSettlement;
use App\Models\CollectorAccount;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class SettlementReversalService
{
    public function void(
        int $settlementId,
        int $stationId,
        int $actorId,
        string $voidedAt,
        string $reason
    ): CollectionSettlement {
        return DB::transaction(function () use ($settlementId, $stationId, $actorId, $voidedAt, $reason) {
            $settlement = CollectionSettlement::query()
                ->whereKey($settlementId)
                ->where('station_id', $stationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($settlement->status === 'voided') {
                return $settlement->load(['journalEntry', 'reversalJournalEntry']);
            }

            if ($settlement->status !== 'posted') {
                throw new CollectionException('Only a posted settlement can be voided.');
            }

            if (!$settlement->journal_entry_id) {
                throw new CollectionException('The settlement has no posted journal entry and cannot be voided safely.');
            }

            $collector = CollectorAccount::query()
                ->whereKey($settlement->collector_account_id)
                ->where('station_id', $stationId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->firstOrFail();

            $cash = CashAccount::query()
                ->whereKey($settlement->cash_account_id)
                ->where('station_id', $stationId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = Decimal::normalize((string) $settlement->amount);

            if (Decimal::compare((string) $cash->balance, $amount) < 0) {
                throw new CollectionException('The settlement cannot be voided because the amount is no longer available in the cash account.');
            }

            if (!$collector->account_id || !$cash->account_id) {
                throw new CollectionException('Settlement GL accounts are not configured.');
            }

            $accounting = app(\App\Services\Accounting\CollectionAccountingService::class);
            $period = $accounting->openPeriodForDate($stationId, $voidedAt);

            $entry = app(\App\Services\Accounting\JournalEntryService::class)->createAndPost(
                $stationId,
                $period->id,
                'REV-SET-' . $settlement->number,
                date('Y-m-d', strtotime($voidedAt)),
                'Reversal of collection settlement ' . $settlement->number,
                [
                    ['account_id' => $collector->account_id, 'debit' => $amount, 'credit' => '0'],
                    ['account_id' => $cash->account_id, 'debit' => '0', 'credit' => $amount],
                ],
                User::find($actorId),
                'collection_settlement_reversal',
                $settlement->id
            );

            $entry->reversal_of_journal_entry_id = $settlement->journal_entry_id;
            $entry->save();

            $cash->balance = Decimal::sub((string) $cash->balance, $amount);
            $cash->save();

            $collector->balance = Decimal::add((string) $collector->balance, $amount);
            $collector->save();

            $settlement->status = 'voided';
            $settlement->voided_by = $actorId;
            $settlement->voided_at = $voidedAt;
            $settlement->void_reason = $reason;
            $settlement->reversal_journal_entry_id = $entry->id;
            $settlement->save();

            return $settlement->fresh(['journalEntry', 'reversalJournalEntry']);
        }, 3);
    }
}
