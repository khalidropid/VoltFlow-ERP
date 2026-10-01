<?php

namespace App\Services\Fuel;

use App\Models\FuelIssue;
use App\Models\FuelReceipt;
use App\Models\FuelStockMovement;
use App\Models\FuelTank;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FuelService
{
    public function postReceipt(int $stationId,int $tankId,int $fuelTypeId,string $uuid,string $receivedAt,string $quantity,string $unitCost): FuelReceipt
    {
        return DB::transaction(function()use($stationId,$tankId,$fuelTypeId,$uuid,$receivedAt,$quantity,$unitCost){
            $existing=FuelReceipt::query()->where('transaction_uuid',$uuid)->first();
            if($existing)return $existing;
            $tank=FuelTank::query()->whereKey($tankId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            $q=Decimal::normalize($quantity); $cost=Decimal::normalize($unitCost);
            $newBalance=Decimal::add((string)$tank->current_quantity,$q);
            if(Decimal::compare($newBalance,$tank->capacity)>0)throw new RuntimeException('Fuel tank capacity would be exceeded.');
            $total=Decimal::multiply($q,$cost);
            $receipt=FuelReceipt::create(['transaction_uuid'=>$uuid,'station_id'=>$stationId,'fuel_type_id'=>$fuelTypeId,'fuel_tank_id'=>$tankId,'received_at'=>$receivedAt,'quantity'=>$q,'unit_cost'=>$cost,'total_cost'=>$total,'status'=>'posted']);
            $tank->update(['current_quantity'=>$newBalance]);
            FuelStockMovement::create(['transaction_uuid'=>$uuid,'station_id'=>$stationId,'fuel_type_id'=>$fuelTypeId,'fuel_tank_id'=>$tankId,'movement_type'=>'receipt','quantity'=>$q,'unit_cost'=>$cost,'reference_type'=>FuelReceipt::class,'reference_id'=>$receipt->id,'moved_at'=>$receivedAt]);
            return $receipt;
        });
    }

    public function postIssue(int $stationId,int $tankId,int $fuelTypeId,string $uuid,string $issuedAt,string $quantity,?int $generatorId=null,string $unitCost='0'): FuelIssue
    {
        return DB::transaction(function()use($stationId,$tankId,$fuelTypeId,$uuid,$issuedAt,$quantity,$generatorId,$unitCost){
            $existing=FuelIssue::query()->where('transaction_uuid',$uuid)->first();
            if($existing)return $existing;
            $tank=FuelTank::query()->whereKey($tankId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            $q=Decimal::normalize($quantity); $cost=Decimal::normalize($unitCost);
            if(Decimal::compare((string)$tank->current_quantity,$q)<0)throw new RuntimeException('Insufficient fuel balance.');
            $total=Decimal::multiply($q,$cost);
            $issue=FuelIssue::create(['transaction_uuid'=>$uuid,'station_id'=>$stationId,'fuel_type_id'=>$fuelTypeId,'fuel_tank_id'=>$tankId,'generator_id'=>$generatorId,'issued_at'=>$issuedAt,'quantity'=>$q,'unit_cost'=>$cost,'total_cost'=>$total,'status'=>'posted']);
            $tank->update(['current_quantity'=>Decimal::sub((string)$tank->current_quantity,$q)]);
            FuelStockMovement::create(['transaction_uuid'=>$uuid,'station_id'=>$stationId,'fuel_type_id'=>$fuelTypeId,'fuel_tank_id'=>$tankId,'movement_type'=>'issue','quantity'=>$q,'unit_cost'=>$cost,'reference_type'=>FuelIssue::class,'reference_id'=>$issue->id,'moved_at'=>$issuedAt]);
            return $issue;
        });
    }
}