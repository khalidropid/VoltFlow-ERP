<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Collections\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function store(Request $request, PaymentService $service): JsonResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'integer'],
            'collector_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
            'invoice_id' => ['nullable', 'integer'],
            'cash_account_id' => ['required', 'integer'],
            'transaction_uuid' => ['required', 'uuid'],
            'receipt_number' => ['required', 'string', 'max:50'],
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'regex:/^\d+(?:\.\d{1,4})?$/'],
            'method' => ['required', 'in:cash,bank,transfer,other'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $service->collect(...$data);

        return response()->json(['data' => $payment], 201);
    }
}
