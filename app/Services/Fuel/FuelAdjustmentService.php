<?php

namespace App\Services\Fuel;

use App\Models\FuelAdjustment;
use App\Models\FuelStockMovement;
use App\Models\FuelTank;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FuelAdjustmentService
{
    public function adjust(int $stationId,int $tankId,string $uuid,string $date,string $quantity,string $reason): FuelAdjustment
    {
        return DB::transaction(function()use($stationId,$tankId,$uuid,$date,$quantity,$reason){
            $existing=FuelAdjustment::query()->where('transaction_uuid',$uuid)->first();
            if($existing)return $existing;
            $tank=FuelTank::query()->whereKey($tankId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            $q=Decimal::normalize($quantity);
            if(Decimal::compare($q,'0')===0)throw new RuntimeException('Adjustment quantity cannot be zero.');
            $new=Decimal::compare($q,'0')>0?Decimal::add((string)$tank->current_quantity,$q):Decimal::sub((string)$tank->current_quantity,Decimal::sub('0',$q));
            if(Decimal::compare($new,'0')<0||Decimal::compare($new,(string)$tank->capacity)>0)throw new RuntimeException('Fuel adjustment would violate tank balance or capacity.');
            $adjustment=FuelAdjustment::create(['transaction_uuid'=>$uuid,'station_id'=>$stationId,'fuel_tank_id'=>$tankId,'adjusted_at'=>$date,'quantity'=>$q,'reason'=>$reason,'status'=>'posted']);
            $tank->update(['current_quantity'=>$new]);
            FuelStockMovement::create(['transaction_uuid'=>$uuid,'station_id'=>$stationId,'fuel_type_id'=>$tank->fuel_type_id,'fuel_tank_id'=>$tank->id,'movement_type'=>'adjustment','quantity'=>$q,'unit_cost'=>'0','reference_type'=>FuelAdjustment::class,'reference_id'=>$adjustment->id,'moved_at'=>$date]);
            return $adjustment;
        });
    }
}