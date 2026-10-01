<?php

namespace App\Services\Generation;

use App\Models\GenerationReading;
use App\Models\Generator;
use App\Models\GeneratorRuntimeLog;
use App\Models\Feeder;
use App\Models\FeederReading;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class GenerationService
{
    public function recordRuntime(int $stationId, int $generatorId, string $startedAt, ?string $stoppedAt = null, ?string $loadPercent = null, ?string $energyKwh = null): GeneratorRuntimeLog
    {
        return DB::transaction(function () use ($stationId, $generatorId, $startedAt, $stoppedAt, $loadPercent, $energyKwh) {
            $generator = Generator::query()->whereKey($generatorId)->where('station_id', $stationId)->lockForUpdate()->firstOrFail();
            if ($generator->status === 'retired') throw new RuntimeException('Retired generators cannot receive runtime logs.');
            if ($stoppedAt !== null && strtotime($stoppedAt) < strtotime($startedAt)) throw new RuntimeException('Stop time cannot precede start time.');

            $hours = null;
            if ($stoppedAt !== null) {
                $seconds = strtotime($stoppedAt) - strtotime($startedAt);
                $wholeHours = intdiv($seconds, 3600);
                $fraction = intdiv(($seconds % 3600) * 10000, 3600);
                $hours = Decimal::normalize($wholeHours . '.' . str_pad((string) $fraction, 4, '0', STR_PAD_LEFT));
            }

            return GeneratorRuntimeLog::create([
                'station_id'=>$stationId,'generator_id'=>$generatorId,'started_at'=>$startedAt,'stopped_at'=>$stoppedAt,
                'hours'=>$hours,'load_percent'=>$loadPercent === null ? null : Decimal::normalize($loadPercent),
                'energy_kwh'=>$energyKwh === null ? null : Decimal::normalize($energyKwh),
            ]);
        });
    }

    public function recordGeneration(int $stationId, int $generatorId, string $transactionUuid, string $readingAt, string $energyKwh, ?string $activePowerKw = null, ?string $reactivePowerKvar = null, string $source = 'manual'): GenerationReading
    {
        return DB::transaction(function () use ($stationId,$generatorId,$transactionUuid,$readingAt,$energyKwh,$activePowerKw,$reactivePowerKvar,$source) {
            $existing=GenerationReading::query()->where('transaction_uuid',$transactionUuid)->first();
            if($existing){
                if($existing->station_id!==$stationId || $existing->generator_id!==$generatorId || Decimal::normalize((string)$existing->energy_kwh)!==Decimal::normalize($energyKwh)) throw new RuntimeException('Transaction UUID is already used for different generation data.');
                return $existing;
            }
            $generator=Generator::query()->whereKey($generatorId)->where('station_id',$stationId)->firstOrFail();
            if($generator->status==='retired') throw new RuntimeException('Retired generators cannot receive readings.');
            return GenerationReading::create([
                'transaction_uuid'=>$transactionUuid,'station_id'=>$stationId,'generator_id'=>$generatorId,'reading_at'=>$readingAt,
                'energy_kwh'=>Decimal::normalize($energyKwh),
                'active_power_kw'=>$activePowerKw===null?null:Decimal::normalize($activePowerKw),
                'reactive_power_kvar'=>$reactivePowerKvar===null?null:Decimal::normalize($reactivePowerKvar),
                'source'=>$source,
            ]);
        });
    }

    public function recordFeederReading(int $stationId,int $feederId,string $transactionUuid,string $readingAt,string $energyKwh,?string $currentAmp=null,?string $voltage=null): FeederReading
    {
        return DB::transaction(function()use($stationId,$feederId,$transactionUuid,$readingAt,$energyKwh,$currentAmp,$voltage){
            $existing=FeederReading::query()->where('transaction_uuid',$transactionUuid)->first();
            if($existing){
                if($existing->station_id!==$stationId || $existing->feeder_id!==$feederId || Decimal::normalize((string)$existing->energy_kwh)!==Decimal::normalize($energyKwh)) throw new RuntimeException('Transaction UUID is already used for different feeder data.');
                return $existing;
            }
            $feeder=Feeder::query()->whereKey($feederId)->where('station_id',$stationId)->firstOrFail();
            if($feeder->status!=='active') throw new RuntimeException('Feeder is not active.');
            return FeederReading::create([
                'transaction_uuid'=>$transactionUuid,'station_id'=>$stationId,'feeder_id'=>$feederId,'reading_at'=>$readingAt,
                'energy_kwh'=>Decimal::normalize($energyKwh),'current_amp'=>$currentAmp===null?null:Decimal::normalize($currentAmp),
                'voltage'=>$voltage===null?null:Decimal::normalize($voltage),
            ]);
        });
    }
}