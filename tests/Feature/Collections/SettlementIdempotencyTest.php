<?php

namespace Tests\Feature\Collections;

use App\Models\CashAccount;
use App\Models\ChartOfAccount;
use App\Models\CollectorAccount;
use App\Models\FiscalPeriod;
use App\Models\Station;
use App\Models\User;
use App\Services\Collections\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettlementIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_settlement_replay_works_after_collector_account_is_closed(): void
    {
        $station = Station::create(['code' => 'ST-SET-001', 'name' => 'Settlement Station']);
        $user = User::create([
            'name' => 'Collector',
            'email' => 'settlement@example.com',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);

        $user->stations()->attach($station, ['is_default' => true]);

        FiscalPeriod::create([
            'station_id' => $station->id,
            'name' => '2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'status' => 'open',
        ]);

        $collectorGl = ChartOfAccount::create([
            'station_id' => $station->id,
            'code' => '1110',
            'name' => 'Collector Cash',
            'type' => 'asset',
        ]);

        $cashGl = ChartOfAccount::create([
            'station_id' => $station->id,
            'code' => '1100',
            'name' => 'Main Cash',
            'type' => 'asset',
        ]);

        $cash = CashAccount::create([
            'station_id' => $station->id,
            'code' => 'CASH-SET-001',
            'name' => 'Main Cash',
            'type' => 'cash',
            'account_id' => $cashGl->id,
            'is_active' => true,
        ]);

        $collector = CollectorAccount::create([
            'station_id' => $station->id,
            'collector_id' => $user->id,
            'cash_account_id' => $cash->id,
            'account_id' => $collectorGl->id,
            'opening_balance' => '40.0000',
            'balance' => '40.0000',
            'status' => 'open',
        ]);

        $uuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

        $first = app(SettlementService::class)->settle(
            $station->id,
            $user->id,
            $cash->id,
            $uuid,
            'SET-REPLAY-001',
            '2026-09-28 12:00:00',
            '40.0000',
            $user->id,
        );

        $collector->update(['status' => 'closed']);

        $second = app(SettlementService::class)->settle(
            $station->id,
            $user->id,
            $cash->id,
            $uuid,
            'SET-REPLAY-001',
            '2026-09-28 12:00:00',
            '40.0000',
            $user->id,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('collection_settlements', 1);
        $this->assertDatabaseCount('journal_entries', 1);
    }
}
