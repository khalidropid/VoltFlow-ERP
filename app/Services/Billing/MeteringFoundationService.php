<?php

namespace App\Services\Billing;

use App\Models\CustomerTariff;
use App\Models\MeterInstallation;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MeteringFoundationService
{
    public function installMeter(int $stationId,int $meterId,int $customerId,string $installedOn,string $initialReading,?string $notes=null): MeterInstallation
    {
        return DB::transaction(function()use($stationId,$meterId,$customerId,$installedOn,$initialReading,$notes){
            $meter=\App\Models\Meter::query()->whereKey($meterId)->where('station_id',$stationId)->firstOrFail();
            $customer=\App\Models\Customer::query()->whereKey($customerId)->where('station_id',$stationId)->firstOrFail();
            if(MeterInstallation::query()->where('station_id',$stationId)->where('meter_id',$meter->id)->whereNull('removed_on')->exists()) throw new RuntimeException('Meter already has an active installation.');
            if(MeterInstallation::query()->where('station_id',$stationId)->where('customer_id',$customer->id)->whereNull('removed_on')->exists()) throw new RuntimeException('Customer already has an active meter installation.');
            $value=Decimal::normalize($initialReading);
            return MeterInstallation::create(['station_id'=>$stationId,'meter_id'=>$meter->id,'customer_id'=>$customer->id,'installed_on'=>$installedOn,'initial_reading'=>$value,'notes'=>$notes]);
        });
    }

    public function removeMeter(int $stationId,int $installationId,string $removedOn): MeterInstallation
    {
        return DB::transaction(function()use($stationId,$installationId,$removedOn){
            $installation=MeterInstallation::query()->whereKey($installationId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if($installation->removed_on) return $installation;
            if($removedOn < $installation->installed_on->toDateString()) throw new RuntimeException('Removal date cannot precede installation date.');
            $installation->update(['removed_on'=>$removedOn]);
            return $installation->fresh();
        });
    }

    public function assignTariff(int $stationId,int $customerId,int $tariffId,string $effectiveFrom,?string $effectiveTo=null): CustomerTariff
    {
        return DB::transaction(function()use($stationId,$customerId,$tariffId,$effectiveFrom,$effectiveTo){
            $customer=\App\Models\Customer::query()->whereKey($customerId)->where('station_id',$stationId)->firstOrFail();
            $tariff=\App\Models\Tariff::query()->whereKey($tariffId)->where('station_id',$stationId)->firstOrFail();
            if($effectiveTo!==null && $effectiveTo < $effectiveFrom) throw new RuntimeException('Tariff end date cannot precede start date.');
            $overlap=CustomerTariff::query()->where('station_id',$stationId)->where('customer_id',$customer->id)->where('is_active',true)
                ->whereDate('effective_from','<=',$effectiveTo ?? '9999-12-31')
                ->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=',$effectiveFrom))
                ->exists();
            if($overlap) throw new RuntimeException('Customer tariff assignment overlaps an existing active assignment.');
            return CustomerTariff::create(['station_id'=>$stationId,'customer_id'=>$customer->id,'tariff_id'=>$tariff->id,'effective_from'=>$effectiveFrom,'effective_to'=>$effectiveTo,'is_active'=>true]);
        });
    }
}