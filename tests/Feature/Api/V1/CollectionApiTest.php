<?php

namespace Tests\Feature\Api\V1;

use App\Models\CashAccount;
use App\Models\ChartOfAccount;
use App\Models\CollectorAccount;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CollectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_api_posts_and_replays_idempotency_key(): void
    {
        [$station, $user, $customer, $invoice, $cash, $collector] = $this->fixture();
        $token = $this->login($user, $station);
        $payload = $this->collectionPayload($station, $user, $customer, $invoice, $cash);

        $first = $this->withToken($token)->withHeader('Idempotency-Key', 'collection-api-001')
            ->postJson('/api/v1/collections', $payload);
        $first->assertCreated()->assertJsonPath('data.amount', '40.0000');

        $second = $this->withToken($token)->withHeader('Idempotency-Key', 'collection-api-001')
            ->postJson('/api/v1/collections', $payload);
        $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('40.0000', (string) $collector->fresh()->balance);
    }

    public function test_idempotency_key_cannot_be_reused_for_a_different_request(): void
    {
        [$station, $user, $customer, $invoice, $cash] = $this->fixture();
        $token = $this->login($user, $station);
        $payload = $this->collectionPayload($station, $user, $customer, $invoice, $cash);

        $this->withToken($token)->withHeader('Idempotency-Key', 'collection-api-002')
            ->postJson('/api/v1/collections', $payload)->assertCreated();

        $payload['amount'] = '41.0000';

        $this->withToken($token)->withHeader('Idempotency-Key', 'collection-api-002')
            ->postJson('/api/v1/collections', $payload)->assertStatus(409);
    }

    public function test_collection_api_rejects_wrong_station(): void
    {
        [$station, $user, $customer, $invoice, $cash] = $this->fixture();
        $otherStation = Station::create(['code' => 'ST-002', 'name' => 'Other Station']);
        $token = $this->login($user, $station);
        $payload = $this->collectionPayload($station, $user, $customer, $invoice, $cash);
        $payload['station_id'] = $otherStation->id;

        $this->withToken($token)->withHeader('Idempotency-Key', 'collection-api-003')
            ->postJson('/api/v1/collections', $payload)->assertForbidden();
    }

    public function test_collection_api_maps_overpayment_to_422(): void
    {
        [$station, $user, $customer, $invoice, $cash] = $this->fixture();
        $token = $this->login($user, $station);
        $payload = $this->collectionPayload($station, $user, $customer, $invoice, $cash);
        $payload['amount'] = '100.0001';

        $this->withToken($token)->withHeader('Idempotency-Key', 'collection-api-004')
            ->postJson('/api/v1/collections', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Payment exceeds the invoice outstanding balance.');
    }

    public function test_settlement_api_posts_and_replays_idempotency_key(): void
    {
        [$station, $user, $customer, $invoice, $cash, $collector] = $this->fixture();
        $collector->update(['balance' => '40.0000']);
        $token = $this->login($user, $station);

        $payload = [
            'station_id' => $station->id, 'collector_id' => $user->id, 'cash_account_id' => $cash->id,
            'transaction_uuid' => '55555555-5555-4555-8555-555555555555', 'number' => 'SET-API-001',
            'settled_at' => '2026-09-28 12:00:00', 'amount' => '40.0000',
        ];

        $first = $this->withToken($token)->withHeader('Idempotency-Key', 'settlement-api-001')
            ->postJson('/api/v1/collection-settlements', $payload);
        $first->assertCreated()->assertJsonPath('data.amount', '40.0000');

        $second = $this->withToken($token)->withHeader('Idempotency-Key', 'settlement-api-001')
            ->postJson('/api/v1/collection-settlements', $payload);
        $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('collection_settlements', 1);
        $this->assertSame('0.0000', (string) $collector->fresh()->balance);
    }

    private function login(User $user, Station $station): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'secret',
            'station_id' => $station->id, 'device_name' => 'api-test-device',
        ])->assertOk()->json('data.token');
    }

   

    private function collectionPayload(Station $station, User $user, Customer $customer, Invoice $invoice, CashAccount $cash): array
    {
        return [
            'station_id' => $station->id, 'collector_id' => $user->id, 'customer_id' => $customer->id,
            'invoice_id' => $invoice->id, 'cash_account_id' => $cash->id,
            'transaction_uuid' => '66666666-6666-4666-8666-666666666666',
            'receipt_number' => 'RCPT-API-001', 'paid_at' => '2026-09-28 10:00:00',
            'amount' => '40.0000', 'method' => 'cash',
        ];
    }

    private function fixture(): array
    {
        $station = Station::create(['code' => 'ST-001', 'name' => 'Main Station']);
        $user = User::create([
            'name' => 'Collector', 'email' => 'collector-api@example.com',
            'password' => Hash::make('secret'), 'is_active' => true,
        ]);
        $user->stations()->attach($station, ['is_default' => true]);

        FiscalPeriod::create([
            'station_id' => $station->id, 'name' => '2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open',
        ]);

        ChartOfAccount::create(['station_id' => $station->id, 'code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset']);
        ChartOfAccount::create(['station_id' => $station->id, 'code' => '4100', 'name' => 'Electricity Revenue', 'type' => 'revenue']);
        $collectorGl = ChartOfAccount::create(['station_id' => $station->id, 'code' => '1110', 'name' => 'Collector Cash', 'type' => 'asset']);
        $cashGl = ChartOfAccount::create(['station_id' => $station->id, 'code' => '1100', 'name' => 'Main Cash', 'type' => 'asset']);

        $cash = CashAccount::create([
            'station_id' => $station->id, 'code' => 'CASH-001', 'name' => 'Main Cash',
            'type' => 'cash', 'account_id' => $cashGl->id, 'is_active' => true,
        ]);
        $collector = CollectorAccount::create([
            'station_id' => $station->id, 'collector_id' => $user->id, 'cash_account_id' => $cash->id,
            'account_id' => $collectorGl->id, 'opening_balance' => '0', 'balance' => '0', 'status' => 'open',
        ]);
        $customer = Customer::create(['station_id' => $station->id, 'code' => 'C-API-001', 'name' => 'API Customer']);
        $invoice = Invoice::create([
            'transaction_uuid' => '77777777-7777-4777-8777-777777777777',
            'station_id' => $station->id, 'customer_id' => $customer->id, 'number' => 'INV-API-001',
            'invoice_date' => '2026-09-28', 'subtotal' => '100.0000', 'discount' => '0',
            'tax' => '0', 'total' => '100.0000', 'paid_amount' => '0', 'status' => 'issued',
        ]);

        return [$station, $user, $customer, $invoice, $cash, $collector];
    }
}
