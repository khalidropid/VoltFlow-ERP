<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\StockMovement;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class InventoryService
{
    public function receive(int $stationId,int $warehouseId,int $itemId,string $uuid,string $quantity,string $unitCost,string $movedAt,?string $referenceType=null,?int $referenceId=null): StockMovement
    {
        return DB::transaction(function()use($stationId,$warehouseId,$itemId,$uuid,$quantity,$unitCost,$movedAt,$referenceType,$referenceId){
            $existing=StockMovement::query()->where('transaction_uuid',$uuid)->first(); if($existing)return $existing;
            $warehouse=Warehouse::query()->whereKey($warehouseId)->where('station_id',$stationId)->firstOrFail();
            if(!$warehouse->is_active)throw new RuntimeException('Warehouse is inactive.');
            Item::query()->whereKey($itemId)->where('station_id',$stationId)->firstOrFail();
            $q=Decimal::normalize($quantity); $cost=Decimal::normalize($unitCost);
            $stock=WarehouseStock::query()->where('warehouse_id',$warehouseId)->where('item_id',$itemId)->lockForUpdate()->first();
            if(!$stock)$stock=WarehouseStock::create(['warehouse_id'=>$warehouseId,'item_id'=>$itemId,'quantity'=>'0.0000','average_cost'=>'0.0000']);
            $oldQty=Decimal::normalize((string)$stock->quantity);
            $oldValue=Decimal::multiply($oldQty,(string)$stock->average_cost);
            $inValue=Decimal::multiply($q,$cost);
            $newQty=Decimal::add($oldQty,$q);
            $avg=$newQty==='0.0000'?'0.0000':Decimal::multiply(Decimal::add($oldValue,$inValue),Decimal::normalize('1.0000'));
            // Keep weighted-average calculation deterministic at DECIMAL(20,4); PostgreSQL performs the division.
            $avg=(string)DB::selectOne('select round((?::numeric / nullif(?::numeric,0)),4) as v', [Decimal::add($oldValue,$inValue),$newQty])->v;
            $movement=StockMovement::create(['transaction_uuid'=>$uuid,'station_id'=>$stationId,'warehouse_id'=>$warehouseId,'item_id'=>$itemId,'movement_type'=>'receipt','quantity'=>$q,'unit_cost'=>$cost,'reference_type'=>$referenceType,'reference_id'=>$referenceId,'moved_at'=>$movedAt]);
            $stock->update(['quantity'=>$newQty,'average_cost'=>Decimal::normalize($avg)]);
            return $movement;
        });
    }

    public function issue(int $stationId,int $warehouseId,int $itemId,string $uuid,string $quantity,string $movedAt,?string $referenceType=null,?int $referenceId=null): StockMovement
    {
        return DB::transaction(function()use($stationId,$warehouseId,$itemId,$uuid,$quantity,$movedAt,$referenceType,$referenceId){
            $existing=StockMovement::query()->where('transaction_uuid',$uuid)->first(); if($existing)return $existing;
            $warehouse=Warehouse::query()->whereKey($warehouseId)->where('station_id',$stationId)->firstOrFail();
            $q=Decimal::normalize($quantity);
            $stock=WarehouseStock::query()->where('warehouse_id',$warehouseId)->where('item_id',$itemId)->lockForUpdate()->firstOrFail();
            if(Decimal::compare((string)$stock->quantity,$q)<0)throw new RuntimeException('Insufficient warehouse stock.');
            $movement=StockMovement::create(['transaction_uuid'=>$uuid,'station_id'=>$stationId,'warehouse_id'=>$warehouseId,'item_id'=>$itemId,'movement_type'=>'issue','quantity'=>$q,'unit_cost'=>$stock->average_cost,'reference_type'=>$referenceType,'reference_id'=>$referenceId,'moved_at'=>$movedAt]);
            $stock->update(['quantity'=>Decimal::sub((string)$stock->quantity,$q)]);
            return $movement;
        });
    }
}