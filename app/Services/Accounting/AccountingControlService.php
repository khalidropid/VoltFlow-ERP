<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AccountingControlService
{
    public function closePeriod(int $stationId,int $periodId,int $userId): FiscalPeriod
    {
        return DB::transaction(function()use($stationId,$periodId){
            $period=FiscalPeriod::query()->whereKey($periodId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if($period->status!=='open')throw new RuntimeException('Only open fiscal periods can be closed.');
            if(JournalEntry::query()->where('station_id',$stationId)->where('fiscal_period_id',$period->id)->where('status','draft')->exists())throw new RuntimeException('Fiscal period contains draft journal entries.');
            $period->update(['status'=>'closed']);
            return $period->fresh();
        });
    }

    public function validateTrialBalance(int $stationId,int $periodId): array
    {
        $period=FiscalPeriod::query()->whereKey($periodId)->where('station_id',$stationId)->firstOrFail();
        $row=DB::table('journal_entry_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')->where('j.station_id',$stationId)->where('j.fiscal_period_id',$period->id)->where('j.status','posted')->selectRaw('COALESCE(SUM(l.debit),0) debit, COALESCE(SUM(l.credit),0) credit')->first();
        $debit=Decimal::normalize((string)$row->debit);$credit=Decimal::normalize((string)$row->credit);
        return ['debit'=>$debit,'credit'=>$credit,'difference'=>Decimal::compare($debit,$credit)>=0?Decimal::sub($debit,$credit):'-'.Decimal::sub($credit,$debit),'balanced'=>Decimal::compare($debit,$credit)===0];
    }
}