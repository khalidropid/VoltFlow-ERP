<?php

namespace Tests\Unit\Accounting;

use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Models\Station;
use App\Services\Accounting\AccountingException;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_an_unbalanced_entry(): void
    {
        $station = Station::create(['code' => 'ST-001', 'name' => 'Main Station']);
        $period = FiscalPeriod::create([
            'station_id' => $station->id, 'name' => '2026', 'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31', 'status' => 'open',
        ]);
        $cash = ChartOfAccount::create(['station_id' => $station->id, 'code' => '1010', 'name' => 'Cash', 'type' => 'asset']);
        $revenue = ChartOfAccount::create(['station_id' => $station->id, 'code' => '4010', 'name' => 'Revenue', 'type' => 'revenue']);

        $this->expectException(AccountingException::class);
        app(JournalEntryService::class)->createAndPost(
            $station->id, $period->id, 'JV-0001', '2026-09-28', 'Invalid test entry',
            [
                ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0'],
                ['account_id' => $revenue->id, 'debit' => '0', 'credit' => '99.99'],
            ]
        );

        $this->assertDatabaseCount('journal_entries', 0);
    }
}
