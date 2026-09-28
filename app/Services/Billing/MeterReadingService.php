<?php

namespace App\Services\Billing;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MeterReadingService
{
    public function record(
        int $stationId, int $meterId, string $transactionUuid, string $readingAt,
        string $readingValue, ?int $userId = null, string $source = 'manual', ?string $notes = null
    ): MeterReading {
        return DB::transaction(function () use ($stationId, $meterId, $transactionUuid, $readingAt, $readingValue, $userId, $source, $notes) {
            $meter = Meter::query()->whereKey($meterId)->where('station_id', $stationId)->lockForUpdate()->firstOrFail();

            if ($meter->status !== 'active') throw new RuntimeException('The meter is not active.');

            $value = Decimal::normalize($readingValue);
            $last = MeterReading::query()->where('meter_id', $meter->id)
                ->where('status', 'validated')->latest('reading_at')->lockForUpdate()->first();

            $previous = $last?->reading_value ?? $meter->initial_reading;
            if (Decimal::compare($value, $previous) < 0) {
                throw new RuntimeException('The new reading cannot be lower than the previous validated reading.');
            }

            $rawConsumption = Decimal::sub($value, $previous);
            $consumption = Decimal::multiplyByInteger($rawConsumption, (int) $meter->multiplier);

            return MeterReading::create([
                'transaction_uuid' => $transactionUuid, 'station_id' => $stationId, 'meter_id' => $meter->id,
                'captured_by' => $userId, 'reading_at' => $readingAt, 'reading_value' => $value,
                'previous_reading_value' => $previous, 'consumption' => $consumption,
                'source' => $source, 'status' => 'validated', 'notes' => $notes,
            ]);
        }, 3);
    }
}
