<?php

namespace App\Services\Collections;

use App\Models\CashAccount;
use App\Models\CollectionSettlement;
use App\Models\CollectorAccount;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function settle(
        int $stationId, int $collectorId, int $cashAccountId, string $transactionUuid,
        string $number, string $settledAt, string $amount, ?int $createdBy = null, ?string $notes = null
    ): CollectionSettlement {
        return DB::transaction(function () use ($stationId, $collectorId, $cashAccountId, $transactionUuid, $number, $settledAt, $amount, $createdBy, $notes) {
            $existing = CollectionSettlement::query()->where('transaction_uuid', $transactionUuid)->first();
            if ($existing) return $existing;

            $collector = CollectorAccount::query()->where('station_id', $stationId)
                ->where('collector_id', $collectorId)->where('status', 'open')->lockForUpdate()->firstOrFail();

            $cash = CashAccount::query()->whereKey($cashAccountId)->where('station_id', $stationId)
                ->where('is_active', true)->lockForUpdate()->firstOrFail();

            $amount = Decimal::normalize($amount);
            if (Decimal::compare($amount, '0') <= 0) throw new CollectionException('Settlement amount must be greater than zero.');
            if (Decimal::compare($amount, (string) $collector->balance) > 0) {
                throw new CollectionException('Settlement cannot exceed the collector outstanding balance.');
            }

            $collector->balance = Decimal::sub((string) $collector->balance, $amount);
            $collector->save();

            return CollectionSettlement::create([
                'transaction_uuid' => $transactionUuid,
                'station_id' => $stationId,
                'collector_account_id' => $collector->id,
                'cash_account_id' => $cash->id,
                'created_by' => $createdBy,
                'number' => $number,
                'settled_at' => $settledAt,
                'amount' => $amount,
                'status' => 'posted',
                'notes' => $notes,
            ]);
        }, 3);
    }
}
