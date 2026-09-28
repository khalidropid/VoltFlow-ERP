<?php

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Models\Station;
use App\Models\User;
use App\Services\Accounting\AccountingException;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JournalEntryHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_rejects_an_account_from_another_station(): void
    {
        [$station, $period] = $this->fixture('ST-JE-001', 'je1@example.com');

        $foreignStation = Station::create(['code' => 'ST-JE-002', 'name' => 'Foreign Station']);
        $foreignAccount = ChartOfAccount::create([
            'station_id' => $foreignStation->id,
            'code' => '1200',
            'name' => 'Foreign Receivable',
            'type' => 'asset',
        ]);

        $this->expectException(AccountingException::class);

        app(JournalEntryService::class)->createAndPost(
            $station->id,
            $period->id,
            'JE-FOREIGN-001',
            '2026-09-28',
            'Foreign account test',
            [
                ['account_id' => $foreignAccount->id, 'debit' => '10.0000', 'credit' => '0'],
                ['account_id' => 1, 'debit' => '0', 'credit' => '10.0000'],
            ],
        );
    }

    public function test_journal_source_can_only_be_posted_once(): void
    {
        [$station, $period] = $this->fixture('ST-JE-003', 'je3@example.com');

        $debit = ChartOfAccount::create([
            'station_id' => $station->id,
            'code' => '1100',
            'name' => 'Cash',
            'type' => 'asset',
        ]);

        $credit = ChartOfAccount::create([
            'station_id' => $station->id,
            'code' => '4100',
            'name' => 'Revenue',
            'type' => 'revenue',
        ]);

        $service = app(JournalEntryService::class);

        $service->createAndPost(
            $station->id,
            $period->id,
            'JE-SOURCE-001',
            '2026-09-28',
            'Source uniqueness test',
            [
                ['account_id' => $debit->id, 'debit' => '10.0000', 'credit' => '0'],
                ['account_id' => $credit->id, 'debit' => '0', 'credit' => '10.0000'],
            ],
            null,
            'test_source',
            999,
        );

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $service->createAndPost(
            $station->id,
            $period->id,
            'JE-SOURCE-002',
            '2026-09-28',
            'Duplicate source test',
            [
                ['account_id' => $debit->id, 'debit' => '10.0000', 'credit' => '0'],
                ['account_id' => $credit->id, 'debit' => '0', 'credit' => '10.0000'],
            ],
            null,
            'test_source',
            999,
        );
    }

    private function fixture(string $code, string $email): array
    {
        $station = Station::create(['code' => $code, 'name' => 'Accounting Station']);
        $user = User::create([
            'name' => 'Accountant',
            'email' => $email,
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);

        $user->stations()->attach($station, ['is_default' => true]);

        $period = FiscalPeriod::create([
            'station_id' => $station->id,
            'name' => '2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'status' => 'open',
        ]);

        return [$station, $period];
    }
}
