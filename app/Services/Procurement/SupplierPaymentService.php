<?php

namespace App\Services\Procurement;

use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Models\SupplierAccountLink;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use App\Models\CashAccount;
use App\Services\Accounting\JournalEntryService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
    ) {}

    public function pay(
        int $stationId,
        int $supplierId,
        int $cashAccountId,
        string $transactionUuid,
        string $receiptNumber,
        string $paidAt,
        string $amount,
        array $allocations,
        string $method = 'cash',
        ?User $actor = null
    ): SupplierPayment {
        return DB::transaction(function () use (
            $stationId,
            $supplierId,
            $cashAccountId,
            $transactionUuid,
            $receiptNumber,
            $paidAt,
            $amount,
            $allocations,
            $method,
            $actor
        ) {
            $existing = SupplierPayment::query()
                ->where('transaction_uuid', $transactionUuid)
                ->first();

            if ($existing) {
                $this->assertSameIdentity(
                    $existing,
                    $stationId,
                    $supplierId,
                    $cashAccountId,
                    $receiptNumber,
                    $paidAt,
                    $amount,
                    $method,
                    $allocations
                );

                return $existing->fresh(['allocations', 'supplier', 'cashAccount', 'journalEntry']);
            }

            if ($allocations === []) {
                throw new ProcurementException('Supplier payment must allocate to at least one invoice.');
            }

            $amount = Decimal::normalize($amount);

            if (Decimal::compare($amount, '0.0000') <= 0) {
                throw new ProcurementException('Supplier payment amount must be greater than zero.');
            }

            $normalizedAllocations = $this->normalizeAllocations($allocations);

            $allocated = '0.0000';
            foreach ($normalizedAllocations as $allocation) {
                $allocated = Decimal::add($allocated, $allocation['amount']);
            }

            if (Decimal::compare($allocated, $amount) !== 0) {
                throw new ProcurementException('Supplier payment allocations must equal the payment amount.');
            }

            $supplier = \App\Models\Supplier::query()
                ->whereKey($supplierId)
                ->where('station_id', $stationId)
                ->where('status', 'active')
                ->first();

            if (! $supplier) {
                throw new ProcurementException('Supplier does not belong to the payment station or is inactive.');
            }

            $link = SupplierAccountLink::query()
                ->with('payableAccount')
                ->where('station_id', $stationId)
                ->where('supplier_id', $supplierId)
                ->first();

            if (! $link) {
                throw new ProcurementException('Supplier accounting link is missing.');
            }

            $this->assertPostableAccount($link->payableAccount, $stationId, 'Supplier payable account');

            $cash = CashAccount::query()
                ->with('glAccount')
                ->whereKey($cashAccountId)
                ->lockForUpdate()
                ->first();

            if (! $cash || $cash->station_id !== $stationId || ! $cash->is_active) {
                throw new ProcurementException('Cash/bank account does not belong to the payment station or is inactive.');
            }

            $this->assertPostableAccount($cash->glAccount, $stationId, 'Cash/bank GL account');

            if (Decimal::compare((string) $cash->balance, $amount) < 0) {
                throw new ProcurementException('Insufficient cash/bank balance for supplier payment.');
            }

            $invoiceIds = array_column($normalizedAllocations, 'supplier_invoice_id');

            $invoices = SupplierInvoice::query()
                ->where('station_id', $stationId)
                ->where('supplier_id', $supplierId)
                ->whereIn('id', $invoiceIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($invoices->count() !== count($invoiceIds)) {
                throw new ProcurementException('One or more supplier invoices are invalid for this supplier or station.');
            }

            foreach ($normalizedAllocations as $allocation) {
                $invoice = $invoices->get($allocation['supplier_invoice_id']);

                if (! in_array($invoice->status, ['posted', 'partially_paid'], true)) {
                    throw new ProcurementException('Supplier invoice is not open for payment.');
                }

                $outstanding = Decimal::sub(
                    Decimal::normalize((string) $invoice->total),
                    Decimal::normalize((string) $invoice->paid_amount)
                );

                if (Decimal::compare($allocation['amount'], $outstanding) > 0) {
                    throw new ProcurementException('Supplier payment allocation exceeds invoice outstanding balance.');
                }
            }

            $period = FiscalPeriod::query()
                ->where('station_id', $stationId)
                ->where('status', 'open')
                ->whereDate('starts_on', '<=', substr($paidAt, 0, 10))
                ->whereDate('ends_on', '>=', substr($paidAt, 0, 10))
                ->first();

            if (! $period) {
                throw new ProcurementException('No open fiscal period covers the supplier payment date.');
            }

            $payment = SupplierPayment::create([
                'transaction_uuid' => $transactionUuid,
                'station_id' => $stationId,
                'supplier_id' => $supplierId,
                'cash_account_id' => $cashAccountId,
                'receipt_number' => $receiptNumber,
                'paid_at' => $paidAt,
                'amount' => $amount,
                'method' => $method,
                'status' => 'posted',
            ]);

            foreach ($normalizedAllocations as $allocation) {
                $invoice = $invoices->get($allocation['supplier_invoice_id']);
                $newPaidAmount = Decimal::add(
                    Decimal::normalize((string) $invoice->paid_amount),
                    $allocation['amount']
                );

                $invoice->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => Decimal::compare($newPaidAmount, (string) $invoice->total) === 0
                        ? 'paid'
                        : 'partially_paid',
                ]);

                $payment->allocations()->create([
                    'supplier_invoice_id' => $invoice->id,
                    'amount' => $allocation['amount'],
                ]);
            }

            $cash->balance = Decimal::sub((string) $cash->balance, $amount);
            $cash->save();

            $entry = $this->journalEntryService->createAndPost(
                $stationId,
                $period->id,
                'SPAY-' . $receiptNumber,
                substr($paidAt, 0, 10),
                'Supplier payment ' . $receiptNumber,
                [
                    [
                        'account_id' => $link->payable_account_id,
                        'debit' => $amount,
                        'credit' => '0.0000',
                        'description' => 'Settle supplier payable',
                        'supplier_id' => $supplierId,
                    ],
                    [
                        'account_id' => $cash->account_id,
                        'debit' => '0.0000',
                        'credit' => $amount,
                        'description' => 'Supplier cash/bank payment',
                        'supplier_id' => $supplierId,
                    ],
                ],
                $actor,
                'supplier_payment',
                $payment->id
            );

            $payment->update(['journal_entry_id' => $entry->id]);

            app(\App\Services\Audit\AuditLogger::class)->record(
                'supplier_payment.posted',
                $payment,
                null,
                $payment->only([
                    'transaction_uuid',
                    'station_id',
                    'supplier_id',
                    'cash_account_id',
                    'amount',
                    'status',
                ]),
                $stationId
            );

            return $payment->fresh(['allocations.supplierInvoice', 'supplier', 'cashAccount', 'journalEntry.lines']);
        }, 3);
    }

    public function void(
        int $supplierPaymentId,
        string $voidedAt,
        ?User $actor = null
    ): SupplierPayment {
        return DB::transaction(function () use ($supplierPaymentId, $voidedAt, $actor) {
            $payment = SupplierPayment::query()
                ->lockForUpdate()
                ->findOrFail($supplierPaymentId);

            if ($payment->status === 'voided') {
                return $payment->fresh(['allocations.supplierInvoice', 'journalEntry', 'reversalJournalEntry']);
            }

            if ($payment->status !== 'posted' || ! $payment->journal_entry_id) {
                throw new ProcurementException('Only posted supplier payments with accounting can be voided.');
            }

            $cash = CashAccount::query()
                ->with('glAccount')
                ->whereKey($payment->cash_account_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cash->station_id !== $payment->station_id || ! $cash->is_active) {
                throw new ProcurementException('Payment cash/bank account does not belong to the station or is inactive.');
            }

            $this->assertPostableAccount($cash->glAccount, $payment->station_id, 'Cash/bank GL account');

            $link = SupplierAccountLink::query()
                ->with('payableAccount')
                ->where('station_id', $payment->station_id)
                ->where('supplier_id', $payment->supplier_id)
                ->firstOrFail();

            $this->assertPostableAccount($link->payableAccount, $payment->station_id, 'Supplier payable account');

            $period = FiscalPeriod::query()
                ->where('station_id', $payment->station_id)
                ->where('status', 'open')
                ->whereDate('starts_on', '<=', substr($voidedAt, 0, 10))
                ->whereDate('ends_on', '>=', substr($voidedAt, 0, 10))
                ->first();

            if (! $period) {
                throw new ProcurementException('No open fiscal period covers the supplier payment reversal date.');
            }

            $allocations = SupplierPaymentAllocation::query()
                ->where('supplier_payment_id', $payment->id)
                ->lockForUpdate()
                ->get();

            if ($allocations->isEmpty()) {
                throw new ProcurementException('Supplier payment has no allocations to reverse.');
            }

            foreach ($allocations as $allocation) {
                $invoice = SupplierInvoice::query()
                    ->whereKey($allocation->supplier_invoice_id)
                    ->where('station_id', $payment->station_id)
                    ->where('supplier_id', $payment->supplier_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (Decimal::compare((string) $invoice->paid_amount, (string) $allocation->amount) < 0) {
                    throw new ProcurementException('Supplier invoice paid balance is inconsistent with the payment allocation.');
                }

                $newPaidAmount = Decimal::sub(
                    Decimal::normalize((string) $invoice->paid_amount),
                    Decimal::normalize((string) $allocation->amount)
                );

                $invoice->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => Decimal::compare($newPaidAmount, '0.0000') === 0
                        ? 'posted'
                        : 'partially_paid',
                ]);
            }

            $cash->balance = Decimal::add((string) $cash->balance, (string) $payment->amount);
            $cash->save();

            $entry = $this->journalEntryService->createAndPost(
                $payment->station_id,
                $period->id,
                'REV-SPAY-' . $payment->id,
                substr($voidedAt, 0, 10),
                'Reverse supplier payment ' . $payment->receipt_number,
                [
                    [
                        'account_id' => $cash->account_id,
                        'debit' => $payment->amount,
                        'credit' => '0.0000',
                        'description' => 'Reverse supplier cash/bank payment',
                        'supplier_id' => $payment->supplier_id,
                    ],
                    [
                        'account_id' => $link->payable_account_id,
                        'debit' => '0.0000',
                        'credit' => $payment->amount,
                        'description' => 'Restore supplier payable',
                        'supplier_id' => $payment->supplier_id,
                    ],
                ],
                $actor,
                'supplier_payment_reversal',
                $payment->id
            );

            $payment->update([
                'status' => 'voided',
                'reversal_journal_entry_id' => $entry->id,
                'voided_at' => $voidedAt,
                'voided_by' => $actor?->id,
            ]);

            app(\App\Services\Audit\AuditLogger::class)->record(
                'supplier_payment.voided',
                $payment,
                ['status' => 'posted'],
                $payment->only(['status', 'voided_at', 'voided_by', 'reversal_journal_entry_id']),
                $payment->station_id
            );

            return $payment->fresh(['allocations.supplierInvoice', 'journalEntry.lines', 'reversalJournalEntry.lines']);
        }, 3);
    }

    private function normalizeAllocations(array $allocations): array
    {
        $result = [];
        $seen = [];

        foreach ($allocations as $allocation) {
            $invoiceId = (int) ($allocation['supplier_invoice_id'] ?? 0);

            if ($invoiceId <= 0 || isset($seen[$invoiceId])) {
                throw new ProcurementException('Supplier payment allocations must reference unique valid invoice IDs.');
            }

            $amount = Decimal::normalize((string) ($allocation['amount'] ?? '0'));

            if (Decimal::compare($amount, '0.0000') <= 0) {
                throw new ProcurementException('Supplier payment allocations must be greater than zero.');
            }

            $seen[$invoiceId] = true;
            $result[] = [
                'supplier_invoice_id' => $invoiceId,
                'amount' => $amount,
            ];
        }

        usort(
            $result,
            fn (array $a, array $b): int => $a['supplier_invoice_id'] <=> $b['supplier_invoice_id']
        );

        return $result;
    }

    private function assertSameIdentity(
        SupplierPayment $existing,
        int $stationId,
        int $supplierId,
        int $cashAccountId,
        string $receiptNumber,
        string $paidAt,
        string $amount,
        string $method,
        array $allocations
    ): void {
        $requestedAllocations = $this->normalizeAllocations($allocations);
        $storedAllocations = $existing->allocations()
            ->get(['supplier_invoice_id', 'amount'])
            ->map(fn ($allocation) => [
                'supplier_invoice_id' => (int) $allocation->supplier_invoice_id,
                'amount' => Decimal::normalize((string) $allocation->amount),
            ])
            ->sortBy('supplier_invoice_id')
            ->values()
            ->all();

        $sameIdentity =
            $existing->station_id === $stationId &&
            $existing->supplier_id === $supplierId &&
            $existing->cash_account_id === $cashAccountId &&
            $existing->receipt_number === $receiptNumber &&
            $existing->paid_at?->format('Y-m-d H:i:s') === date('Y-m-d H:i:s', strtotime($paidAt)) &&
            Decimal::normalize((string) $existing->amount) === Decimal::normalize($amount) &&
            $existing->method === $method &&
            $storedAllocations === $requestedAllocations;

        if (! $sameIdentity) {
            throw new ProcurementException('The transaction UUID is already associated with a different supplier payment.');
        }
    }

    private function assertPostableAccount(?ChartOfAccount $account, int $stationId, string $label): void
    {
        if (
            ! $account ||
            ! $account->is_active ||
            ! $account->is_postable ||
            ! ($account->station_id === null || (int) $account->station_id === $stationId)
        ) {
            throw new ProcurementException($label . ' is not active/postable for the payment station.');
        }
    }
}
