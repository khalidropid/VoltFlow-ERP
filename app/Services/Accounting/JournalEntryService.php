<?php

namespace App\Services\Accounting;

use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalEntryService
{
    public function createAndPost(
        int $stationId, int $periodId, string $number, string $date, string $description,
        array $lines, ?User $actor = null, ?string $sourceType = null, ?int $sourceId = null
    ): JournalEntry {
        if (count($lines) < 2) throw new AccountingException('A journal entry requires at least two lines.');

        $period = FiscalPeriod::query()->whereKey($periodId)->where('station_id', $stationId)
            ->where('status', 'open')->firstOrFail();

        if ($date < $period->starts_on->format('Y-m-d') || $date > $period->ends_on->format('Y-m-d')) {
            throw new AccountingException('The journal date is outside the open fiscal period.');
        }

        [$debit, $credit] = $this->totals($lines);
        if ($debit !== $credit) throw new AccountingException("Unbalanced journal entry: debit={$debit}, credit={$credit}.");
        if ($debit === '0.0000') throw new AccountingException('A zero-value journal entry cannot be posted.');

        return DB::transaction(function () use ($stationId, $periodId, $number, $date, $description, $lines, $actor, $sourceType, $sourceId) {
            $entry = JournalEntry::create([
                'station_id' => $stationId, 'fiscal_period_id' => $periodId, 'number' => $number,
                'entry_date' => $date, 'description' => $description, 'status' => 'posted',
                'source_type' => $sourceType, 'source_id' => $sourceId,
                'created_by' => $actor?->id, 'posted_by' => $actor?->id, 'posted_at' => now(),
            ]);

            foreach (array_values($lines) as $index => $line) {
                $debit = $this->normalize($line['debit'] ?? '0');
                $credit = $this->normalize($line['credit'] ?? '0');
                if ($debit !== '0.0000' && $credit !== '0.0000') {
                    throw new AccountingException('A journal line cannot contain both debit and credit.');
                }
                if ($debit === '0.0000' && $credit === '0.0000') {
                    throw new AccountingException('A journal line must contain a debit or credit.');
                }
                $entry->lines()->create([
                    'account_id' => $line['account_id'], 'debit' => $debit, 'credit' => $credit,
                    'description' => $line['description'] ?? null, 'line_no' => $index + 1,
                ]);
            }
            return $entry->load('lines');
        });
    }

    private function totals(array $lines): array
    {
        $debit = '0.0000'; $credit = '0.0000';
        foreach ($lines as $line) {
            $debit = $this->add($debit, $this->normalize($line['debit'] ?? '0'));
            $credit = $this->add($credit, $this->normalize($line['credit'] ?? '0'));
        }
        return [$debit, $credit];
    }

    private function normalize(string|int|float $value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException('Financial amounts must be non-negative decimals with up to four decimals.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        return $whole . '.' . str_pad($fraction, 4, '0');
    }

    private function add(string $a, string $b): string
    {
        [$aw, $af] = explode('.', $a); [$bw, $bf] = explode('.', $b);
        $fraction = (int) $af + (int) $bf;
        $carry = intdiv($fraction, 10000);
        $fraction %= 10000;
        $whole = $this->addIntegers($aw, $bw, $carry);
        return $whole . '.' . str_pad((string) $fraction, 4, '0', STR_PAD_LEFT);
    }

    private function addIntegers(string $a, string $b, int $carry = 0): string
    {
        $i = strlen($a) - 1; $j = strlen($b) - 1; $result = ''; $carry += 0;
        while ($i >= 0 || $j >= 0 || $carry > 0) {
            $sum = $carry + ($i >= 0 ? ord($a[$i--]) - 48 : 0) + ($j >= 0 ? ord($b[$j--]) - 48 : 0);
            $result = ($sum % 10) . $result; $carry = intdiv($sum, 10);
        }
        return ltrim($result, '0') ?: '0';
    }
}
