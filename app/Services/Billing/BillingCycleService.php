<?php

namespace App\Services\Billing;

use App\Models\BillingCycle;
use App\Models\BillingPeriod;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BillingCycleService
{
    public function openPeriod(int $stationId, string $code, string $name, string $startsOn, string $endsOn): BillingPeriod
    {
        if ($endsOn < $startsOn) {
            throw new RuntimeException('Billing period end date cannot precede start date.');
        }

        return DB::transaction(function () use ($stationId, $code, $name, $startsOn, $endsOn) {
            return BillingPeriod::create([
                'station_id' => $stationId,
                'code' => $code,
                'name' => $name,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => 'open',
            ]);
        });
    }

    public function createCycle(
        int $stationId,
        int $periodId,
        string $code,
        string $name,
        string $readingFrom,
        string $readingTo,
        ?string $billingDate = null
    ): BillingCycle {
        return DB::transaction(function () use ($stationId, $periodId, $code, $name, $readingFrom, $readingTo, $billingDate) {
            $period = BillingPeriod::query()
                ->whereKey($periodId)
                ->where('station_id', $stationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($period->status !== 'open') {
                throw new RuntimeException('Only open billing periods can accept cycles.');
            }
            if ($readingTo < $readingFrom || $readingFrom < $period->starts_on->toDateString() || $readingTo > $period->ends_on->toDateString()) {
                throw new RuntimeException('Billing cycle reading range must be inside the billing period.');
            }

            return BillingCycle::create([
                'station_id' => $stationId,
                'billing_period_id' => $period->id,
                'code' => $code,
                'name' => $name,
                'reading_from' => $readingFrom,
                'reading_to' => $readingTo,
                'billing_date' => $billingDate,
                'status' => 'draft',
            ]);
        });
    }

    public function closePeriod(int $stationId, int $periodId): BillingPeriod
    {
        return DB::transaction(function () use ($stationId, $periodId) {
            $period = BillingPeriod::query()->whereKey($periodId)->where('station_id', $stationId)->lockForUpdate()->firstOrFail();
            if ($period->cycles()->whereIn('status', ['draft', 'processing'])->exists()) {
                throw new RuntimeException('Billing period has unfinished cycles.');
            }
            return tap($period)->update(['status' => 'closed']);
        });
    }

    public function completeCycle(int $stationId, int $cycleId): BillingCycle
    {
        return DB::transaction(function () use ($stationId, $cycleId) {
            $cycle = BillingCycle::query()->whereKey($cycleId)->where('station_id', $stationId)->lockForUpdate()->firstOrFail();
            if ($cycle->status !== 'processing') {
                throw new RuntimeException('Only processing cycles can be completed.');
            }
            return tap($cycle)->update(['status' => 'completed']);
        });
    }

    public function startCycle(int $stationId, int $cycleId): BillingCycle
    {
        return DB::transaction(function () use ($stationId, $cycleId) {
            $cycle = BillingCycle::query()->whereKey($cycleId)->where('station_id', $stationId)->lockForUpdate()->firstOrFail();
            if ($cycle->status !== 'draft') {
                throw new RuntimeException('Only draft cycles can be started.');
            }
            return tap($cycle)->update(['status' => 'processing']);
        });
    }

    public function cycleConsumption(int $stationId, int $cycleId): string
    {
        $cycle = BillingCycle::query()->whereKey($cycleId)->where('station_id', $stationId)->firstOrFail();
        return $cycle->invoices()->where('status', '!=', 'void')->get()->reduce(
            fn (string $total, Invoice $invoice): string => Decimal::add($total, (string) $invoice->consumption),
            '0.0000'
        );
    }
}