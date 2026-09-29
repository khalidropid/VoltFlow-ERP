<?php

namespace App\Services\Procurement;

use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Models\GoodsReceipt;
use App\Models\SupplierAccountLink;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\User;
use App\Services\Accounting\JournalEntryService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class SupplierInvoiceService
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
    ) {}

    public function post(int $supplierInvoiceId, ?User $actor = null): SupplierInvoice
    {
        return DB::transaction(function () use ($supplierInvoiceId, $actor) {
            $invoice = SupplierInvoice::query()
                ->lockForUpdate()
                ->findOrFail($supplierInvoiceId);

            if ($invoice->status === 'posted' && $invoice->journal_entry_id) {
                return $invoice->fresh(['supplier', 'goodsReceipt', 'items', 'journalEntry.lines']);
            }

            if ($invoice->status !== 'draft') {
                throw new ProcurementException('Only draft supplier invoices can be posted.');
            }

            $supplier = $invoice->supplier()->firstOrFail();

            if ($supplier->station_id !== $invoice->station_id || $supplier->status !== 'active') {
                throw new ProcurementException('Supplier invoice supplier is invalid for the invoice station.');
            }

            $link = SupplierAccountLink::query()
                ->with(['payableAccount', 'expenseAccount', 'taxAccount'])
                ->where('station_id', $invoice->station_id)
                ->where('supplier_id', $supplier->id)
                ->first();

            if (! $link) {
                throw new ProcurementException('Supplier accounting link is missing.');
            }

            $this->assertPostableAccount($link->payableAccount, $invoice->station_id, 'Supplier payable account');

            $items = $invoice->items()
                ->orderBy('line_no')
                ->get();

            if ($items->isEmpty()) {
                throw new ProcurementException('Supplier invoice must contain at least one line.');
            }

            $receipt = null;

            if ($invoice->goods_receipt_id !== null) {
                $receipt = GoodsReceipt::query()
                    ->lockForUpdate()
                    ->findOrFail($invoice->goods_receipt_id);

                if ($receipt->station_id !== $invoice->station_id || $receipt->status !== 'posted') {
                    throw new ProcurementException('Linked goods receipt is not posted for this supplier invoice.');
                }

                if ($receipt->purchase_order_id !== null) {
                    $purchaseOrder = $receipt->purchaseOrder()->firstOrFail();
                    if ($purchaseOrder->supplier_id !== $supplier->id) {
                        throw new ProcurementException('Goods receipt supplier does not match the supplier invoice.');
                    }
                }

                $existing = SupplierInvoice::query()
                    ->where('goods_receipt_id', $receipt->id)
                    ->where('id', '!=', $invoice->id)
                    ->whereIn('status', ['posted', 'partially_paid', 'paid'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw new ProcurementException('A posted supplier invoice already exists for this goods receipt.');
                }

                $this->assertMatchesGoodsReceipt($items, $receipt);
            }

            $subtotal = '0.0000';
            $journalLines = [];

            foreach ($items as $line) {
                $quantity = Decimal::normalize((string) $line->quantity);
                $unitCost = Decimal::normalize((string) $line->unit_cost);
                $amount = Decimal::normalize((string) $line->amount);

                if (Decimal::compare($quantity, '0.0000') <= 0) {
                    throw new ProcurementException('Supplier invoice quantities must be greater than zero.');
                }

                $expectedAmount = $this->roundedProduct($quantity, $unitCost);

                if (Decimal::compare($amount, $expectedAmount) !== 0) {
                    throw new ProcurementException('Supplier invoice line amount does not match quantity multiplied by unit cost.');
                }

                $subtotal = Decimal::add($subtotal, $amount);

                $item = $line->item()->first();

                if ($item && $item->station_id !== $invoice->station_id) {
                    throw new ProcurementException('Supplier invoice contains an item from another station.');
                }

                if ($item && $item->item_type === 'stock') {
                    if (! $receipt) {
                        throw new ProcurementException('Stock supplier invoice lines require a posted goods receipt.');
                    }

                    $account = $item->inventoryAccount()->first();

                    if (! $account) {
                        $warehouseAccount = $receipt->warehouse()->first()?->inventoryAccount()->first();
                        $account = $warehouseAccount;
                    }

                    $this->assertPostableAccount($account, $invoice->station_id, 'Inventory account');

                    $journalLines[] = [
                        'account_id' => $account->id,
                        'debit' => $amount,
                        'credit' => '0.0000',
                        'description' => $line->description,
                        'supplier_id' => $supplier->id,
                    ];
                } else {
                    $account = $item?->expense_account_id
                        ? $item->expenseAccount()->first()
                        : $link->expenseAccount;

                    $this->assertPostableAccount($account, $invoice->station_id, 'Expense account');

                    $journalLines[] = [
                        'account_id' => $account->id,
                        'debit' => $amount,
                        'credit' => '0.0000',
                        'description' => $line->description,
                        'supplier_id' => $supplier->id,
                    ];
                }
            }

            $tax = Decimal::normalize((string) $invoice->tax);
            $total = Decimal::normalize((string) $invoice->total);
            $storedSubtotal = Decimal::normalize((string) $invoice->subtotal);

            if (Decimal::compare($storedSubtotal, $subtotal) !== 0) {
                throw new ProcurementException('Supplier invoice subtotal does not match its lines.');
            }

            $expectedTotal = Decimal::add($subtotal, $tax);

            if (Decimal::compare($total, $expectedTotal) !== 0) {
                throw new ProcurementException('Supplier invoice total does not equal subtotal plus tax.');
            }

            if (Decimal::compare($total, '0.0000') <= 0) {
                throw new ProcurementException('Supplier invoice total must be greater than zero.');
            }

            if (Decimal::compare($tax, '0.0000') > 0) {
                $this->assertPostableAccount($link->taxAccount, $invoice->station_id, 'Input tax account');

                $journalLines[] = [
                    'account_id' => $link->tax_account_id,
                    'debit' => $tax,
                    'credit' => '0.0000',
                    'description' => 'Input tax',
                    'supplier_id' => $supplier->id,
                ];
            }

            $period = FiscalPeriod::query()
                ->where('station_id', $invoice->station_id)
                ->where('status', 'open')
                ->whereDate('starts_on', '<=', $invoice->invoice_date->format('Y-m-d'))
                ->whereDate('ends_on', '>=', $invoice->invoice_date->format('Y-m-d'))
                ->first();

            if (! $period) {
                throw new ProcurementException('No open fiscal period covers the supplier invoice date.');
            }

            $journalLines[] = [
                'account_id' => $link->payable_account_id,
                'debit' => '0.0000',
                'credit' => $total,
                'description' => 'Supplier payable',
                'supplier_id' => $supplier->id,
            ];

            $entry = $this->journalEntryService->createAndPost(
                $invoice->station_id,
                $period->id,
                'SINVOICE-' . $invoice->number,
                $invoice->invoice_date->format('Y-m-d'),
                'Supplier invoice ' . $invoice->number,
                $journalLines,
                $actor,
                'supplier_invoice',
                $invoice->id
            );

            $invoice->update([
                'status' => 'posted',
                'journal_entry_id' => $entry->id,
            ]);

            app(\App\Services\Audit\AuditLogger::class)->record(
                'supplier_invoice.posted',
                $invoice,
                ['status' => 'draft', 'journal_entry_id' => null],
                ['status' => 'posted', 'journal_entry_id' => $entry->id],
                $invoice->station_id
            );

            return $invoice->fresh(['supplier', 'goodsReceipt', 'items', 'journalEntry.lines']);
        }, 3);
    }

    private function assertMatchesGoodsReceipt($invoiceItems, GoodsReceipt $receipt): void
    {
        $receiptItems = $receipt->items()->orderBy('line_no')->get();

        if ($invoiceItems->count() !== $receiptItems->count()) {
            throw new ProcurementException('Supplier invoice lines do not match the goods receipt.');
        }

        $receiptByItem = $receiptItems->keyBy('item_id');

        foreach ($invoiceItems as $line) {
            if ($line->item_id === null || ! $receiptByItem->has($line->item_id)) {
                throw new ProcurementException('Supplier invoice line does not match the goods receipt item.');
            }

            $receiptLine = $receiptByItem->get($line->item_id);

            if (
                Decimal::compare($line->quantity, $receiptLine->quantity) !== 0 ||
                Decimal::compare($line->unit_cost, $receiptLine->unit_cost) !== 0 ||
                Decimal::compare($line->amount, $receiptLine->amount) !== 0
            ) {
                throw new ProcurementException('Supplier invoice line quantity or cost does not match the goods receipt.');
            }
        }
    }

    private function roundedProduct(string $a, string $b): string
    {
        $value = DB::query()
            ->selectRaw('ROUND(? * ?, 4) AS value', [$a, $b])
            ->value('value');

        return Decimal::normalize((string) $value);
    }

    private function assertPostableAccount(?ChartOfAccount $account, int $stationId, string $label): void
    {
        if (
            ! $account ||
            ! $account->is_active ||
            ! $account->is_postable ||
            ! ($account->station_id === null || (int) $account->station_id === $stationId)
        ) {
            throw new ProcurementException($label . ' is not active/postable for the invoice station.');
        }
    }
}
