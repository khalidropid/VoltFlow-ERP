<?php

namespace Tests\Feature\Models;

use App\Models\AccountingRule;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\BankTransaction;
use App\Models\CashAccount;
use App\Models\CashClosing;
use App\Models\CashMovement;
use App\Models\ChartOfAccount;
use App\Models\CollectionSettlement;
use App\Models\CollectorAccount;
use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\CustomerAccountLink;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5AccountingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_and_collection_models_are_station_scoped_and_related(): void
    {
        $stationA = Station::create([
            'code' => 'STA',
            'name' => 'Station A',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
        ]);

        $stationB = Station::create([
            'code' => 'STB',
            'name' => 'Station B',
            'timezone' => 'Asia/Aden',
            'currency_code' => 'YER',
        ]);

        $assetA = ChartOfAccount::create([
            'station_id' => $stationA->id,
            'code' => '1100',
            'name' => 'Cash',
            'type' => 'asset',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $assetB = ChartOfAccount::create([
            'station_id' => $stationB->id,
            'code' => '1100',
            'name' => 'Cash',
            'type' => 'asset',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $cash = CashAccount::create([
            'station_id' => $stationA->id,
            'code' => 'CASH-001',
            'name' => 'Main Cash',
            'type' => 'cash',
            'account_id' => $assetA->id,
            'balance' => '0.0000',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $collectorAccount = CollectorAccount::create([
            'station_id' => $stationA->id,
            'collector_id' => $user->id,
            'cash_account_id' => $cash->id,
            'account_id' => $assetA->id,
            'opening_balance' => '0.0000',
            'balance' => '25.0000',
            'status' => 'open',
        ]);

        $period = FiscalPeriod::create([
            'station_id' => $stationA->id,
            'name' => '2026-09',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
            'status' => 'open',
        ]);

        $entry = JournalEntry::create([
            'station_id' => $stationA->id,
            'fiscal_period_id' => $period->id,
            'number' => 'JV-0001',
            'entry_date' => '2026-09-29',
            'description' => 'Test collection entry',
            'status' => 'posted',
        ]);

        $costCenter = CostCenter::create([
            'station_id' => $stationA->id,
            'code' => 'CC-001',
            'name' => 'Operations',
            'is_active' => true,
        ]);

        $entry->lines()->create([
            'account_id' => $assetA->id,
            'cost_center_id' => $costCenter->id,
            'debit' => '25.0000',
            'credit' => '0.0000',
            'line_no' => 1,
        ]);

        $entry->lines()->create([
            'account_id' => $assetA->id,
            'cost_center_id' => $costCenter->id,
            'debit' => '0.0000',
            'credit' => '25.0000',
            'line_no' => 2,
        ]);

        $settlement = CollectionSettlement::create([
            'transaction_uuid' => '11111111-1111-4111-8111-111111111111',
            'station_id' => $stationA->id,
            'collector_account_id' => $collectorAccount->id,
            'cash_account_id' => $cash->id,
            'created_by' => $user->id,
            'number' => 'SET-0001',
            'settled_at' => '2026-09-29 10:00:00',
            'amount' => '25.0000',
            'status' => 'posted',
            'journal_entry_id' => $entry->id,
        ]);

        $customer = Customer::create([
            'station_id' => $stationA->id,
            'code' => 'CUS-001',
            'name' => 'Customer A',
            'status' => 'active',
            'opening_balance' => '0.0000',
        ]);

        $customerLink = CustomerAccountLink::create([
            'station_id' => $stationA->id,
            'customer_id' => $customer->id,
            'receivable_account_id' => $assetA->id,
            'revenue_account_id' => $assetA->id,
        ]);

        $globalRule = AccountingRule::create([
            'station_id' => null,
            'transaction_type' => 'global_test',
            'debit_account_id' => $assetA->id,
            'credit_account_id' => $assetA->id,
            'is_active' => true,
        ]);

        $stationRule = AccountingRule::create([
            'station_id' => $stationA->id,
            'transaction_type' => 'station_test',
            'debit_account_id' => $assetA->id,
            'credit_account_id' => $assetA->id,
            'is_active' => true,
        ]);

        $bankTransaction = BankTransaction::create([
            'transaction_uuid' => '22222222-2222-4222-8222-222222222222',
            'station_id' => $stationA->id,
            'cash_account_id' => $cash->id,
            'transaction_date' => '2026-09-29',
            'description' => 'Bank test transaction',
            'debit' => '10.0000',
            'credit' => '0.0000',
            'status' => 'unreconciled',
        ]);

        $reconciliation = BankReconciliation::create([
            'station_id' => $stationA->id,
            'cash_account_id' => $cash->id,
            'statement_date' => '2026-09-29',
            'statement_balance' => '10.0000',
            'book_balance' => '10.0000',
            'difference' => '0.0000',
            'status' => 'completed',
        ]);

        $reconciliationItem = BankReconciliationItem::create([
            'bank_reconciliation_id' => $reconciliation->id,
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => '10.0000',
        ]);

        CashMovement::create([
            'transaction_uuid' => '33333333-3333-4333-8333-333333333333',
            'station_id' => $stationA->id,
            'cash_account_id' => $cash->id,
            'movement_type' => 'deposit',
            'amount' => '25.0000',
            'moved_at' => '2026-09-29 10:00:00',
            'created_by' => $user->id,
        ]);

        CashClosing::create([
            'station_id' => $stationA->id,
            'cash_account_id' => $cash->id,
            'closing_date' => '2026-09-29',
            'system_balance' => '25.0000',
            'counted_balance' => '25.0000',
            'variance' => '0.0000',
            'status' => 'closed',
            'closed_by' => $user->id,
            'closed_at' => '2026-09-29 23:00:00',
        ]);

        $this->assertSame([$assetA->id], ChartOfAccount::forStation($stationA->id)->pluck('id')->all());
        $this->assertNotContains($assetB->id, ChartOfAccount::forStation($stationA->id)->pluck('id')->all());

        $entry->load(['station', 'fiscalPeriod', 'lines.account', 'lines.costCenter']);
        $settlement->load(['station', 'collectorAccount.cashAccount', 'cashAccount', 'journalEntry']);
        $cash->load(['glAccount', 'collectorAccounts', 'settlements', 'movements', 'closings', 'bankTransactions', 'bankReconciliations']);
        $reconciliation->load(['cashAccount', 'items.bankTransaction']);
        $customerLink->load(['customer', 'receivableAccount', 'revenueAccount']);

        $this->assertSame($stationA->id, $entry->station->id);
        $this->assertSame($period->id, $entry->fiscalPeriod->id);
        $this->assertCount(2, $entry->lines);
        $this->assertSame($assetA->id, $entry->lines->first()->account->id);
        $this->assertSame($costCenter->id, $entry->lines->first()->costCenter->id);

        $this->assertSame($collectorAccount->id, $settlement->collectorAccount->id);
        $this->assertSame($cash->id, $settlement->cashAccount->id);
        $this->assertSame($entry->id, $settlement->journalEntry->id);

        $this->assertSame($assetA->id, $cash->glAccount->id);
        $this->assertCount(1, $cash->collectorAccounts);
        $this->assertCount(1, $cash->settlements);
        $this->assertCount(1, $cash->movements);
        $this->assertCount(1, $cash->closings);
        $this->assertCount(1, $cash->bankTransactions);
        $this->assertCount(1, $cash->bankReconciliations);

        $this->assertSame($bankTransaction->id, $reconciliation->items->first()->bankTransaction->id);
        $this->assertSame($customer->id, $customerLink->customer->id);
        $this->assertSame($assetA->id, $customerLink->receivableAccount->id);

        $ruleIds = AccountingRule::forStationOrGlobal($stationA->id)->pluck('id')->all();
        $this->assertContains($globalRule->id, $ruleIds);
        $this->assertContains($stationRule->id, $ruleIds);
    }
}
