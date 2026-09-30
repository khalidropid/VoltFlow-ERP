<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MeterReading;
use App\Services\Billing\BillingService;
use App\Services\Billing\MeterReadingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BillingController extends Controller
{
    public function reading(Request $request, MeterReadingService $service): JsonResponse
    {
        $data=$request->validate([
            'station_id'=>['required','integer'],'meter_id'=>['required','integer'],'transaction_uuid'=>['required','uuid'],
            'reading_at'=>['required','date'],'reading_value'=>['required','regex:/^\d+(?:\.\d{1,4})?$/'],
            'source'=>['nullable','in:manual,mobile,imported'],'notes'=>['nullable','string'],
        ]);
        $reading=$service->record($data['station_id'],$data['meter_id'],$data['transaction_uuid'],$data['reading_at'],$data['reading_value'],$request->user()->id,$data['source']??'mobile',$data['notes']??null);
        return response()->json(['data'=>$reading],201);
    }

    public function invoice(Request $request, BillingService $service): JsonResponse
    {
        $data=$request->validate([
            'station_id'=>['required','integer'],'reading_id'=>['required','integer'],'invoice_uuid'=>['required','uuid'],
            'invoice_number'=>['required','string','max:50'],'invoice_date'=>['required','date'],'due_date'=>['nullable','date'],
        ]);
        $reading=MeterReading::query()->whereKey($data['reading_id'])->where('station_id',$data['station_id'])->firstOrFail();
        $invoice=$service->issueForReading($data['station_id'],$reading,$data['invoice_uuid'],$data['invoice_number'],$data['invoice_date'],$data['due_date']??null);
        return response()->json(['data'=>$invoice->load('items')],201);
    }
}