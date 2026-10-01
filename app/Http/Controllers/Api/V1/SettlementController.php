<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Collections\SettlementReversalService;
use App\Services\Collections\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function store(Request $request, SettlementService $service): JsonResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'integer'],
            'collector_id' => ['required', 'integer'],
            'cash_account_id' => ['required', 'integer'],
            'transaction_uuid' => ['required', 'uuid'],
            'number' => ['required', 'string', 'max:50'],
            'settled_at' => ['required', 'date'],
            'amount' => ['required', 'regex:/^\d+(?:\.\d{1,4})?$/'],
            'notes' => ['nullable', 'string'],
        ]);

        $settlement = $service->settle(
            $data['station_id'], $data['collector_id'], $data['cash_account_id'],
            $data['transaction_uuid'], $data['number'], $data['settled_at'],
            $data['amount'], $request->user()->id, $data['notes'] ?? null
        );

        return response()->json(['data' => $settlement], 201);
    }

    public function void(Request $request, int $settlement, SettlementReversalService $service): JsonResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'integer'],
            'voided_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $settlementModel = $service->void(
            settlementId: $settlement,
            stationId: $data['station_id'],
            actorId: $request->user()->id,
            voidedAt: $data['voided_at'],
            reason: $data['reason'],
        );

        return response()->json(['data' => $settlementModel]);
    }
}
