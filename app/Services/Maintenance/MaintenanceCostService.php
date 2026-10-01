<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceWorkOrder;
use App\Models\MaintenanceWorkOrderLabor;
use App\Models\MaintenanceWorkOrderPart;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MaintenanceCostService
{
    public function addPart(int $stationId,int $workOrderId,int $itemId,string $quantity,string $unitCost): MaintenanceWorkOrderPart
    {
        return DB::transaction(function()use($stationId,$workOrderId,$itemId,$quantity,$unitCost){
            $work=MaintenanceWorkOrder::query()->whereKey($workOrderId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if(in_array($work->status,['completed','cancelled'],true))throw new RuntimeException('Closed work orders cannot receive parts.');
            $q=Decimal::normalize($quantity);$cost=Decimal::normalize($unitCost);
            if(Decimal::compare($q,'0')<=0||Decimal::compare($cost,'0')<0)throw new RuntimeException('Invalid maintenance part quantity or cost.');
            return MaintenanceWorkOrderPart::create(['maintenance_work_order_id'=>$work->id,'item_id'=>$itemId,'quantity'=>$q,'unit_cost'=>$cost,'total_cost'=>Decimal::multiply($q,$cost)]);
        });
    }

    public function addLabor(int $stationId,int $workOrderId,?int $employeeId,string $hours,string $rate): MaintenanceWorkOrderLabor
    {
        return DB::transaction(function()use($stationId,$workOrderId,$employeeId,$hours,$rate){
            $work=MaintenanceWorkOrder::query()->whereKey($workOrderId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if(in_array($work->status,['completed','cancelled'],true))throw new RuntimeException('Closed work orders cannot receive labor.');
            $h=Decimal::normalize($hours);$r=Decimal::normalize($rate);
            if(Decimal::compare($h,'0')<=0||Decimal::compare($r,'0')<0)throw new RuntimeException('Invalid labor hours or rate.');
            return MaintenanceWorkOrderLabor::create(['maintenance_work_order_id'=>$work->id,'employee_id'=>$employeeId,'hours'=>$h,'hourly_rate'=>$r,'total_cost'=>Decimal::multiply($h,$r)]);
        });
    }
}