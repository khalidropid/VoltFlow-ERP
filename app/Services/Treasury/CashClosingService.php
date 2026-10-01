<?php

namespace App\Services\Treasury;

use App\Models\CashAccount;
use App\Models\CashClosing;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CashClosingService
{
    public function close(int $stationId,int $cashAccountId,string $date,string $countedBalance,int $userId): CashClosing
    {
        return DB::transaction(function()use($stationId,$cashAccountId,$date,$countedBalance,$userId){
            $cash=CashAccount::query()->whereKey($cashAccountId)->where('station_id',$stationId)->where('is_active',true)->lockForUpdate()->firstOrFail();
            $existing=CashClosing::query()->where('station_id',$stationId)->where('cash_account_id',$cash->id)->whereDate('closing_date',$date)->lockForUpdate()->first();
            if($existing?->status==='closed') return $existing;
            $counted=Decimal::normalize($countedBalance);
            $system=Decimal::normalize((string)$cash->balance);
            $variance=Decimal::compare($counted,$system)>=0 ? Decimal::sub($counted,$system) : '-'.Decimal::sub($system,$counted);
            $data=['station_id'=>$stationId,'cash_account_id'=>$cash->id,'closing_date'=>$date,'system_balance'=>$system,'counted_balance'=>$counted,'variance'=>$variance,'closed_by'=>$userId,'closed_at'=>now(),'status'=>'closed'];
            return $existing ? tap($existing)->update($data) : CashClosing::create($data);
        });
    }

    public function reopen(int $stationId,int $closingId,int $userId): CashClosing
    {
        return DB::transaction(function()use($stationId,$closingId,$userId){
            $closing=CashClosing::query()->whereKey($closingId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if($closing->status!=='closed') throw new RuntimeException('Only closed cash closings can be reopened.');
            $closing->update(['status'=>'reopened','closed_by'=>$userId,'closed_at'=>now()]);
            return $closing->fresh();
        });
    }
}