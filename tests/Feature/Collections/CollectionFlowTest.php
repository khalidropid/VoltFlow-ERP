<?php

namespace Tests\Feature\Collections;

use App\Models\CashAccount;
use App\Models\ChartOfAccount;
use App\Models\CollectorAccount;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\Station;
use App\Models\User;
use App\Services\Collections\CollectionException;
use App\Services\Collections\PaymentService;
use App\Services\Collections\SettlementService;
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
    }

    public function test_settlement_cannot_exceed_collector_balance(): void
    {
        [$station, $user, $collector, $cash] = $this->fixture();
        $collector->update(['balance' => '25.0000']);

        $this->expectException(CollectionException::class);

        app(SettlementService::class)->settle(
            $station->id, $user->id, $cash->id,
            '33333333-3333-4333-8333-333333333333', 'SET-001',
            '2026-09-28 12:00:00', '25.0001', $user->id
        );
    }

    private function fixture(): array
    {
        $station = Station::create(['code' => 'ST-001', 'name' => 'Main Station']);
        $user = User::create(['name' => 'Collector', 'email' => 'collector@example.com', 'password' => Hash::make('secret'), 'is_active' => true]);
        $user->stations()->attach($station, ['is_default' => true]);

        FiscalPeriod::create(['station_id' => $station->id, 'name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
        $ar = ChartOfAccount::create(['station_id' => $station->id, 'code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset']);
        $revenue = ChartOfAccount::create(['station_id' => $station->id, 'code' => '4100', 'name' => 'Electricity Revenue', 'type' => 'revenue']);
        $collectorGl = ChartOfAccount::create(['station_id' => $station->id, 'code' => '1110', 'name' => 'Collector Cash', 'type' => 'asset']);
        $cashGl = ChartOfAccount::create(['station_id' => $station->id, 'code' => '1100', 'name' => 'Main Cash', 'type' => 'asset']);

        $cash = CashAccount::create(['station_id' => $station->id, 'code' => 'CASH-001', 'name' => 'Main Cash', 'type' => 'cash', 'account_id' => $cashGl->id, 'is_active' => true]);
        $collector = CollectorAccount::create(['station_id' => $station->id, 'collector_id' => $user->id, 'cash_account_id' => $cash->id, 'account_id' => $collectorGl->id, 'opening_balance' => '0', 'balance' => '0', 'status' => 'open']);

        return [$station, $user, $collector, $cash];
    }
}
