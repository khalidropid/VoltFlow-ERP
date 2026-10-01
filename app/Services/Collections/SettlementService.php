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
        int $stationId,
        int $collectorId,
        int $cashAccountId,
        string $transactionUuid,
        string $number,
        string $settledAt,
        string $amount,
        ?int $createdBy = null,
        ?string $notes = null
    ): CollectionSettlement {
        return DB::transaction(function () use (
            $stationId,
            $collectorId,
            $cashAccountId,
            $transactionUuid,
            $number,
            $settledAt,
            $amount,
            $createdBy,
            $notes
        ) {
            /*
             * collectorId = users.id
             * collectorAccountId = collector_accounts.id
             *
             * These are different IDs and must never be compared directly.
             */

            $collectorAccount = CollectorAccount::query()
                ->where('station_id', $stationId)
                ->where('collector_id', $collectorId)
                ->first();

            if (!$collectorAccount) {
                throw new CollectionException(
                    'The collector does not have a collection account.'
                );
            }

            /*
             * Idempotency check before requiring the collector account
             * to be open.
             *
             * This allows an already completed settlement to be replayed
             * even if the collector account has subsequently been closed.
             */
            $existing = CollectionSettlement::query()
                ->where('transaction_uuid', $transactionUuid)
                ->first();

            if ($existing) {
                $this->assertSameIdentity(
                    $existing,
                    $stationId,
                    $collectorAccount->id,
                    $cashAccountId,
                    $number,
                    $settledAt,
                    $amount
                );

                return $existing;
            }

            /*
             * Lock the collector account for a new settlement.
             *
             * The account must be open when creating a new settlement.
             */
            $collector = CollectorAccount::query()
                ->whereKey($collectorAccount->id)
                ->where('station_id', $stationId)
                ->where('collector_id', $collectorId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (!$collector) {
                throw new CollectionException(
                    'The collector does not have an open collection account.'
                );
            }

            /*
             * Re-check the transaction UUID after acquiring the lock.
             *
             * This protects against concurrent requests using the same UUID.
             */
            $existing = CollectionSettlement::query()
                ->where('transaction_uuid', $transactionUuid)
                ->first();

            if ($existing) {
                $this->assertSameIdentity(
                    $existing,
                    $stationId,
                    $collector->id,
                    $cashAccountId,
                    $number,
                    $settledAt,
                    $amount
                );

                return $existing;
            }

            /*
             * Lock the destination cash/bank account.
             */
            $cash = CashAccount::query()
                ->whereKey($cashAccountId)
                ->where('station_id', $stationId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$cash) {
                throw new CollectionException(
                    'The cash/bank account is not active for this station.'
                );
            }

            /*
             * Normalize the amount before all financial calculations.
             */
            $amount = Decimal::normalize($amount);

            if (Decimal::compare($amount, '0') <= 0) {
                throw new CollectionException(
                    'Settlement amount must be greater than zero.'
                );
            }

            /*
             * A collector cannot settle more than the outstanding
             * balance held in their collection account.
             */
            if (Decimal::compare($amount, (string) $collector->balance) > 0) {
                throw new CollectionException(
                    'Settlement cannot exceed the collector outstanding balance.'
                );
            }

            /*
             * Decrease collector custody balance.
             */
            $collector->balance = Decimal::sub(
                (string) $collector->balance,
                $amount
            );

            $collector->save();

            /*
             * Increase cash/bank account balance.
             */
            $cash->balance = Decimal::add(
                (string) $cash->balance,
                $amount
            );

            $cash->save();

            /*
             * Create the settlement transaction.
             */
            $settlement = CollectionSettlement::create([
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

            /*
             * Post the corresponding double-entry accounting journal.
             */
            return app(
                \App\Services\Accounting\CollectionAccountingService::class
            )->postSettlement(
                $settlement,
                $createdBy
            );
        }, 3);
    }

    /**
     * Verify that an existing settlement belongs to exactly
     * the same business operation as the current request.
     *
     * This prevents transaction UUID reuse for a different settlement.
     */
    private function assertSameIdentity(
        CollectionSettlement $existing,
        int $stationId,
        int $collectorAccountId,
        int $cashAccountId,
        string $number,
        string $settledAt,
        string $amount
    ): void {
        $sameIdentity =
            $existing->station_id === $stationId &&
            $existing->collector_account_id === $collectorAccountId &&
            $existing->cash_account_id === $cashAccountId &&
            $existing->number === $number &&
            $existing->settled_at?->format('Y-m-d H:i:s') ===
                date('Y-m-d H:i:s', strtotime($settledAt)) &&
            Decimal::normalize((string) $existing->amount) ===
                Decimal::normalize($amount);

        if (!$sameIdentity) {
            throw new CollectionException(
                'The transaction UUID is already associated with a different settlement.'
            );
        }
    }
}