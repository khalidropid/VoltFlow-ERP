<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Generation\GenerationService;
use App\Services\Generation\DistributionService;
use App\Services\Fuel\FuelService;
use App\Services\Fuel\FuelAdjustmentService;
use Illuminate\Http\Request;

final class OperationsController
{
    public function generation(Request $request, GenerationService $service)
    {
        $data=$request->validate(['generator_id'=>'required|integer','transaction_uuid'=>'required|uuid','reading_at'=>'required|date','energy_kwh'=>'required','active_power_kw'=>'nullable','reactive_power_kvar'=>'nullable','source'=>'nullable|string']);
        $r=$service->recordGeneration((int)$request->user()->stations()->firstOrFail()->id,$data['generator_id'],$data['transaction_uuid'],$data['reading_at'],$data['energy_kwh'],$data['active_power_kw']??null,$data['reactive_power_kvar']??null,$data['source']??'api');
        return response()->json($r,201);
    }

    public function fuelReceipt(Request $request,FuelService $service)
    {
        $data=$request->validate(['tank_id'=>'required|integer','fuel_type_id'=>'required|integer','transaction_uuid'=>'required|uuid','received_at'=>'required|date','quantity'=>'required','unit_cost'=>'required']);
        $stationId=$request->user()->stations()->firstOrFail()->id;
        return response()->json($service->postReceipt($stationId,...array_values($data)),201);
    }

    public function fuelIssue(Request $request,FuelService $service)
    {
        $data=$request->validate(['tank_id'=>'required|integer','fuel_type_id'=>'required|integer','transaction_uuid'=>'required|uuid','issued_at'=>'required|date','quantity'=>'required','generator_id'=>'nullable|integer','unit_cost'=>'nullable']);
        $stationId=$request->user()->stations()->firstOrFail()->id;
        return response()->json($service->postIssue($stationId,$data['tank_id'],$data['fuel_type_id'],$data['transaction_uuid'],$data['issued_at'],$data['quantity'],$data['generator_id']??null,$data['unit_cost']??'0'),201);
    }

    public function fuelAdjustment(Request $request,FuelAdjustmentService $service)
    {
        $data=$request->validate(['tank_id'=>'required|integer','transaction_uuid'=>'required|uuid','date'=>'required|date','quantity'=>'required','reason'=>'required|string']);
        $stationId=$request->user()->stations()->firstOrFail()->id;
        return response()->json($service->adjust($stationId,$data['tank_id'],$data['transaction_uuid'],$data['date'],$data['quantity'],$data['reason']),201);
    }
}