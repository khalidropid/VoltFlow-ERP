<?php

namespace Tests\Feature\Collections;

use App\Models\CashAccount;
use App\Models\ChartOfAccount;
use App\Models\CollectorAccount;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Station;
use App\Models\User;
use App\Services\Collections\CollectionException;
use App\Services\Collections\PaymentReversalService;
use App\Services\Collections\PaymentService;
use App\Services\Collections\SettlementService;
use App\Support\Decimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CollectionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_increases_collector_balance_and_is_posted_to_gl(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();

        $customer = Customer::create(['station_id' => $station->id, 'code' => 'C-001', 'name' => 'Customer']);
        $invoice = Invoice::create([
            'transaction_uuid' => '11111111-1111-4111-8111-111111111111',
            'station_id' => $station->id, 'customer_id' => $customer->id, 'number' => 'INV-001',
            'invoice_date' => '2026-09-28', 'subtotal' => '100.0000', 'discount' => '0',
            'tax' => '0', 'total' => '100.0000', 'paid_amount' => '0', 'status' => 'issued',
        ]);

        $payment = app(PaymentService::class)->collect(
            $station->id, $user->id, $customer->id, $invoice->id, $cash->id,
            '22222222-2222-4222-8222-222222222222', 'RCPT-001', '2026-09-28 10:00:00',
            '40.0000', 'cash'
        );

        $this->assertSame('40.0000', (string) $collector->fresh()->balance);
        $this->assertNotNull($payment->journal_entry_id);
        $this->assertSame('40.0000', (string) $invoice->fresh()->paid_amount);
        $this->assertSame('partially_paid', $invoice->fresh()->status);

        $entry = JournalEntry::with('lines')->findOrFail($payment->journal_entry_id);
        $debit = array_reduce($entry->lines->all(), fn (string $sum, $line) => Decimal::add($sum, (string) $line->debit), '0.0000');
        $credit = array_reduce($entry->lines->all(), fn (string $sum, $line) => Decimal::add($sum, (string) $line->credit), '0.0000');
        $this->assertSame('40.0000', $debit);
        $this->assertSame('40.0000', $credit);
    }

    public function test_duplicate_payment_uuid_is_idempotent_and_does_not_double_collect(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $customer = Customer::create(['station_id' => $station->id, 'code' => 'C-001', 'name' => 'Customer']);
        $invoice = Invoice::create([
            'transaction_uuid' => '11111111-1111-4111-8111-111111111111',
            'station_id' => $station->id, 'customer_id' => $customer->id, 'number' => 'INV-001',
            'invoice_date' => '2026-09-28', 'subtotal' => '100.0000', 'discount' => '0',
            'tax' => '0', 'total' => '100.0000', 'paid_amount' => '0', 'status' => 'issued',
        ]);

        $uuid = '22222222-2222-4222-8222-222222222222';
        $first = app(PaymentService::class)->collect($station->id, $user->id, $customer->id, $invoice->id, $cash->id, $uuid, 'RCPT-001', '2026-09-28 10:00:00', '40.0000');
        $second = app(PaymentService::class)->collect($station->id, $user->id, $customer->id, $invoice->id, $cash->id, $uuid, 'RCPT-001', '2026-09-28 10:00:00', '40.0000');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('40.0000', (string) $collector->fresh()->balance);
        $this->assertSame('40.0000', (string) $invoice->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_payment_uuid_cannot_be_reused_for_a_different_business_operation(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $customer = Customer::create(['station_id' => $station->id, 'code' => 'C-001', 'name' => 'Customer']);
        $otherCustomer = Customer::create(['station_id' => $station->id, 'code' => 'C-002', 'name' => 'Other Customer']);

        $invoice = Invoice::create([
            'transaction_uuid' => '11111111-1111-4111-8111-111111111111',
            'station_id' => $station->id, 'customer_id' => $customer->id, 'number' => 'INV-001',
            'invoice_date' => '2026-09-28', 'subtotal' => '100.0000', 'discount' => '0',
            'tax' => '0', 'total' => '100.0000', 'paid_amount' => '0', 'status' => 'issued',
        ]);

        $uuid = '22222222-2222-4222-8222-222222222222';

        app(PaymentService::class)->collect(
            $station->id, $user->id, $customer->id, $invoice->id, $cash->id,
            $uuid, 'RCPT-001', '2026-09-28 10:00:00', '40.0000'
        );

        $this->expectException(CollectionException::class);

        app(PaymentService::class)->collect(
            $station->id, $user->id, $otherCustomer->id, $invoice->id, $cash->id,
            $uuid, 'RCPT-002', '2026-09-28 10:00:00', '40.0000'
        );
    }

    public function test_payment_cannot_exceed_invoice_balance(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $customer = Customer::create(['station_id' => $station->id, 'code' => 'C-001', 'name' => 'Customer']);
        $invoice = Invoice::create([
            'transaction_uuid' => '11111111-1111-4111-8111-111111111111',
            'station_id' => $station->id, 'customer_id' => $customer->id, 'number' => 'INV-001',
            'invoice_date' => '2026-09-28', 'subtotal' => '100.0000', 'discount' => '0',
            'tax' => '0', 'total' => '100.0000', 'paid_amount' => '90.0000', 'status' => 'partially_paid',
        ]);

        $this->expectException(CollectionException::class);
        app(PaymentService::class)->collect($station->id, $user->id, $customer->id, $invoice->id, $cash->id, '22222222-2222-4222-8222-222222222222', 'RCPT-001', '2026-09-28 10:00:00', '10.0001');
    }

    public function test_successful_settlement_reduces_collector_balance_and_posts_to_gl(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $collector->update(['balance' => '40.0000']);

        $settlement = app(SettlementService::class)->settle(
            $station->id, $user->id, $cash->id, '33333333-3333-4333-8333-333333333333',
            'SET-001', '2026-09-28 12:00:00', '40.0000', $user->id
        );

        $this->assertSame('0.0000', (string) $collector->fresh()->balance);
        $this->assertNotNull($settlement->journal_entry_id);
        $this->assertDatabaseCount('collection_settlements', 1);
        $this->assertDatabaseCount('journal_entries', 1);

        $entry = JournalEntry::with('lines')->findOrFail($settlement->journal_entry_id);
        $debit = array_reduce($entry->lines->all(), fn (string $sum, $line) => Decimal::add($sum, (string) $line->debit), '0.0000');
        $credit = array_reduce($entry->lines->all(), fn (string $sum, $line) => Decimal::add($sum, (string) $line->credit), '0.0000');
        $this->assertSame('40.0000', $debit);
        $this->assertSame('40.0000', $credit);
    }

    public function test_payment_void_reverses_invoice_collector_and_posts_balanced_reversal(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $customer = Customer::create(['station_id' => $station->id, 'code' => 'C-001', 'name' => 'Customer']);
        $invoice = Invoice::create([
            'transaction_uuid' => '11111111-1111-4111-8111-111111111111',
            'station_id' => $station->id, 'customer_id' => $customer->id, 'number' => 'INV-001',
            'invoice_date' => '2026-09-28', 'subtotal' => '100.0000', 'discount' => '0',
            'tax' => '0', 'total' => '100.0000', 'paid_amount' => '0', 'status' => 'issued',
        ]);

        $payment = app(PaymentService::class)->collect(
            $station->id, $user->id, $customer->id, $invoice->id, $cash->id,
            '22222222-2222-4222-8222-222222222222', 'RCPT-001', '2026-09-28 10:00:00', '40.0000'
        );

        $voided = app(PaymentReversalService::class)->void(
            $payment->id, $station->id, $user->id, '2026-09-28 13:00:00', 'Duplicate receipt'
        );

        $this->assertSame('voided', $voided->status);
        $this->assertNotNull($voided->reversal_journal_entry_id);
        $this->assertSame('0.0000', (string) $collector->fresh()->balance);
        $this->assertSame('0.0000', (string) $invoice->fresh()->paid_amount);
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 2);

        $entry = JournalEntry::with('lines')->findOrFail($voided->reversal_journal_entry_id);
        $this->assertSame($payment->journal_entry_id, $entry->reversal_of_journal_entry_id);
        $debit = array_reduce($entry->lines->all(), fn (string $sum, $line) => Decimal::add($sum, (string) $line->debit), '0.0000');
        $credit = array_reduce($entry->lines->all(), fn (string $sum, $line) => Decimal::add($sum, (string) $line->credit), '0.0000');
        $this->assertSame('40.0000', $debit);
        $this->assertSame('40.0000', $credit);
    }

    public function test_payment_void_is_idempotent_after_first_void(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $customer = Customer::create(['station_id' => $station->id, 'code' => 'C-001', 'name' => 'Customer']);
        $invoice = Invoice::create([
            'transaction_uuid' => '11111111-1111-4111-8111-111111111111',
            'station_id' => $station->id, 'customer_id' => $customer->id, 'number' => 'INV-001',
            'invoice_date' => '2026-09-28', 'subtotal' => '100.0000', 'discount' => '0',
            'tax' => '0', 'total' => '100.0000', 'paid_amount' => '0', 'status' => 'issued',
        ]);

        $payment = app(PaymentService::class)->collect(
            $station->id, $user->id, $customer->id, $invoice->id, $cash->id,
            '22222222-2222-4222-8222-222222222222', 'RCPT-001', '2026-09-28 10:00:00', '40.0000'
        );

        $first = app(PaymentReversalService::class)->void($payment->id, $station->id, $user->id, '2026-09-28 13:00:00', 'Duplicate receipt');
        $second = app(PaymentReversalService::class)->void($payment->id, $station->id, $user->id, '2026-09-28 14:00:00', 'Second request');

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->reversal_journal_entry_id, $second->reversal_journal_entry_id);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertSame('0.0000', (string) $collector->fresh()->balance);
    }

    public function test_payment_void_is_rejected_after_collector_settlement(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $customer = Customer::create(['station_id' => $station->id, 'code' => 'C-001', 'name' => 'Customer']);
        $invoice = Invoice::create([
            'transaction_uuid' => '11111111-1111-4111-8111-111111111111',
            'station_id' => $station->id, 'customer_id' => $customer->id, 'number' => 'INV-001',
            'invoice_date' => '2026-09-28', 'subtotal' => '100.0000', 'discount' => '0',
            'tax' => '0', 'total' => '100.0000', 'paid_amount' => '0', 'status' => 'issued',
        ]);

        $payment = app(PaymentService::class)->collect(
            $station->id, $user->id, $customer->id, $invoice->id, $cash->id,
            '22222222-2222-4222-8222-222222222222', 'RCPT-001', '2026-09-28 10:00:00', '40.0000'
        );

        app(SettlementService::class)->settle(
            $station->id, $user->id, $cash->id, '33333333-3333-4333-8333-333333333333',
            'SET-001', '2026-09-28 12:00:00', '40.0000', $user->id
        );

        $this->expectException(CollectionException::class);
        app(PaymentReversalService::class)->void($payment->id, $station->id, $user->id, '2026-09-28 13:00:00', 'Attempt after settlement');
    }

    public function test_settlement_void_restores_collector_and_reverses_cash(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $collector->update(['balance' => '40.0000']);

        $settlement = app(SettlementService::class)->settle(
            $station->id, $user->id, $cash->id,
            '55555555-5555-4555-8555-555555555555', 'SET-VOID-001',
            '2026-09-28 12:00:00', '40.0000', $user->id
        );

        $this->assertSame('40.0000', (string) $cash->fresh()->balance);
        $this->assertSame('0.0000', (string) $collector->fresh()->balance);

        $voided = app(\App\Services\Collections\SettlementReversalService::class)->void(
            $settlement->id, $station->id, $user->id, '2026-09-28 13:00:00', 'Duplicate settlement'
        );

        $this->assertSame('voided', $voided->status);
        $this->assertNotNull($voided->reversal_journal_entry_id);
        $this->assertSame('0.0000', (string) $cash->fresh()->balance);
        $this->assertSame('40.0000', (string) $collector->fresh()->balance);
        $this->assertDatabaseCount('journal_entries', 2);

        $entry = JournalEntry::with('lines')->findOrFail($voided->reversal_journal_entry_id);
        $this->assertSame($settlement->journal_entry_id, $entry->reversal_of_journal_entry_id);
        $debit = array_reduce($entry->lines->all(), fn (string $sum, $line) => Decimal::add($sum, (string) $line->debit), '0.0000');
        $credit = array_reduce($entry->lines->all(), fn (string $sum, $line) => Decimal::add($sum, (string) $line->credit), '0.0000');
        $this->assertSame('40.0000', $debit);
        $this->assertSame('40.0000', $credit);
    }

    public function test_settlement_void_is_rejected_when_cash_has_been_used(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $collector->update(['balance' => '40.0000']);

        $settlement = app(SettlementService::class)->settle(
            $station->id, $user->id, $cash->id,
            '66666666-6666-4666-8666-666666666666', 'SET-VOID-002',
            '2026-09-28 12:00:00', '40.0000', $user->id
        );

        $cash->update(['balance' => '10.0000']);

        $this->expectException(CollectionException::class);
        app(\App\Services\Collections\SettlementReversalService::class)->void(
            $settlement->id, $station->id, $user->id, '2026-09-28 13:00:00', 'Cash already used'
        );
    }

    public function test_settlement_void_is_idempotent_after_first_void(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $collector->update(['balance' => '40.0000']);

        $settlement = app(SettlementService::class)->settle(
            $station->id, $user->id, $cash->id,
            '77777777-7777-4777-8777-777777777777', 'SET-VOID-003',
            '2026-09-28 12:00:00', '40.0000', $user->id
        );

        $service = app(\App\Services\Collections\SettlementReversalService::class);
        $first = $service->void($settlement->id, $station->id, $user->id, '2026-09-28 13:00:00', 'Duplicate settlement');
        $second = $service->void($settlement->id, $station->id, $user->id, '2026-09-28 14:00:00', 'Second request');

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->reversal_journal_entry_id, $second->reversal_journal_entry_id);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertSame('40.0000', (string) $cash->fresh()->balance);
        $this->assertSame('40.0000', (string) $collector->fresh()->balance);
    }

    public function test_duplicate_settlement_uuid_is_idempotent(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $collector->update(['balance' => '40.0000']);

        $uuid = '33333333-3333-4333-8333-333333333333';
        $first = app(SettlementService::class)->settle($station->id, $user->id, $cash->id, $uuid, 'SET-001', '2026-09-28 12:00:00', '40.0000', $user->id);
        $second = app(SettlementService::class)->settle($station->id, $user->id, $cash->id, $uuid, 'SET-001', '2026-09-28 12:00:00', '40.0000', $user->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('0.0000', (string) $collector->fresh()->balance);
        $this->assertDatabaseCount('collection_settlements', 1);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_settlement_cannot_exceed_collector_balance(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $collector->update(['balance' => '25.0000']);

        $this->expectException(CollectionException::class);

        app(SettlementService::class)->settle(
            $station->id, $user->id, $cash->id,
            '44444444-4444-4444-8444-444444444444', 'SET-001',
            '2026-09-28 12:00:00', '25.0001', $user->id
        );
    }

    private function fixture(): array
    {
        $station = Station::create(['code' => 'ST-001', 'name' => 'Main Station']);
        $user = User::create(['name' => 'Collector', 'email' => 'collector@example.com', 'password' => Hash::make('secret'), 'is_active' => true]);
        $user->stations()->attach($station, ['is_default' => true]);

        FiscalPeriod::create(['station_id' => $station->id, 'name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
        ChartOfAccount::create(['station_id' => $station->id, 'code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset']);
        ChartOfAccount::create(['station_id' => $station->id, 'code' => '4100', 'name' => 'Electricity Revenue', 'type' => 'revenue']);
        $collectorGl = ChartOfAccount::create(['station_id' => $station->id, 'code' => '1110', 'name' => 'Collector Cash', 'type' => 'asset']);
        $cashGl = ChartOfAccount::create(['station_id' => $station->id, 'code' => '1100', 'name' => 'Main Cash', 'type' => 'asset']);

        $cash = CashAccount::create(['station_id' => $station->id, 'code' => 'CASH-001', 'name' => 'Main Cash', 'type' => 'cash', 'account_id' => $cashGl->id, 'is_active' => true]);
        $collector = CollectorAccount::create(['station_id' => $station->id, 'collector_id' => $user->id, 'cash_account_id' => $cash->id, 'account_id' => $collectorGl->id, 'opening_balance' => '0', 'balance' => '0', 'status' => 'open']);

        return [$station, $user, $collector, $cash];
    }
}
