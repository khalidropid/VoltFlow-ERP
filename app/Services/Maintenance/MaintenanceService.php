<?php

namespace App\Services\Maintenance;

use App\Models\Asset;
use App\Models\MaintenanceWorkOrder;
use App\Models\MaintenanceWorkOrderExpense;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MaintenanceService
{
    public function openWorkOrder(int $stationId,int $assetId,string $uuid,string $number,string $title,string $openedOn,string $type='corrective',string $priority='normal'): MaintenanceWorkOrder
    {
        return DB::transaction(function()use($stationId,$assetId,$uuid,$number,$title,$openedOn,$type,$priority){
            $asset=Asset::query()->whereKey($assetId)->where('station_id',$stationId)->firstOrFail();
            if($asset->status==='disposed'||$asset->status==='retired')throw new RuntimeException('Disposed or retired assets cannot receive work orders.');
            return MaintenanceWorkOrder::create(['transaction_uuid'=>$uuid,'station_id'=>$stationId,'asset_id'=>$assetId,'number'=>$number,'title'=>$title,'opened_on'=>$openedOn,'type'=>$type,'priority'=>$priority,'status'=>'open']);
        });
    }

    public function completeWorkOrder(int $stationId,int $workOrderId,string $completedOn,?string $resolution=null): MaintenanceWorkOrder
    {
        return DB::transaction(function()use($stationId,$workOrderId,$completedOn,$resolution){
            $work=MaintenanceWorkOrder::query()->whereKey($workOrderId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if(!in_array($work->status,['open','in_progress'],true))throw new RuntimeException('Work order is not open for completion.');
            $work->update(['status'=>'completed','completed_on'=>$completedOn,'resolution'=>$resolution]);
            return $work->fresh();
        });
    }

    public function addExpense(int $stationId,int $workOrderId,string $description,string $amount): MaintenanceWorkOrderExpense
    {
        return DB::transaction(function()use($stationId,$workOrderId,$description,$amount){
            $work=MaintenanceWorkOrder::query()->whereKey($workOrderId)->where('station_id',$stationId)->firstOrFail();
            if($work->status==='completed'||$work->status==='cancelled')throw new RuntimeException('Completed or cancelled work orders cannot receive expenses.');
            $value=Decimal::normalize($amount);
            if(Decimal::compare($value,'0')<=0)throw new RuntimeException('Expense amount must be positive.');
            return MaintenanceWorkOrderExpense::create(['maintenance_work_order_id'=>$work->id,'description'=>$description,'amount'=>$value]);
        });
    }
}