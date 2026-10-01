<?php

namespace App\Services\Generation;

use App\Models\CustomerConnection;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DistributionService
{
    public function connect(int $stationId,int $customerId,int $meterId,int $feederId,string $connectedOn,string $loadKw): CustomerConnection
    {
        return DB::transaction(function()use($stationId,$customerId,$meterId,$feederId,$connectedOn,$loadKw){
            $customer=\App\Models\Customer::query()->whereKey($customerId)->where('station_id',$stationId)->firstOrFail();
            $meter=\App\Models\Meter::query()->whereKey($meterId)->where('station_id',$stationId)->firstOrFail();
            $feeder=\App\Models\Feeder::query()->whereKey($feederId)->where('station_id',$stationId)->where('status','active')->firstOrFail();
            if(CustomerConnection::query()->where('station_id',$stationId)->where('customer_id',$customer->id)->where('connection_status','connected')->exists()) throw new RuntimeException('Customer already has an active connection.');
            $load=Decimal::normalize($loadKw);
            if(Decimal::compare($load,'0')<=0) throw new RuntimeException('Connection load must be positive.');
            if(Decimal::compare($load,(string)$feeder->capacity_kw)>0) throw new RuntimeException('Connection load exceeds feeder capacity.');
            return CustomerConnection::create(['station_id'=>$stationId,'customer_id'=>$customer->id,'meter_id'=>$meter->id,'feeder_id'=>$feeder->id,'connected_on'=>$connectedOn,'connection_status'=>'connected','connection_load_kw'=>$load]);
        });
    }

    public function disconnect(int $stationId,int $connectionId,string $date): CustomerConnection
    {
        return DB::transaction(function()use($stationId,$connectionId,$date){
            $connection=CustomerConnection::query()->whereKey($connectionId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if($connection->connection_status==='disconnected') return $connection;
            if($date<$connection->connected_on->toDateString()) throw new RuntimeException('Disconnect date cannot precede connection date.');
            $connection->update(['connection_status'=>'disconnected','disconnected_on'=>$date]);
            return $connection->fresh();
        });
    }
}