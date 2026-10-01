<?php

namespace App\Services\Treasury;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\BankTransaction;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BankReconciliationService
{
    public function start(int $stationId,int $cashAccountId,string $statementDate,string $statementBalance,int $userId): BankReconciliation
    {
        return BankReconciliation::create(['station_id'=>$stationId,'cash_account_id'=>$cashAccountId,'statement_date'=>$statementDate,'statement_balance'=>Decimal::normalize($statementBalance),'book_balance'=>'0.0000','difference'=>Decimal::normalize($statementBalance),'status'=>'in_progress','prepared_by'=>$userId]);
    }

    public function match(int $stationId,int $reconciliationId,int $bankTransactionId,string $amount): BankReconciliationItem
    {
        return DB::transaction(function()use($stationId,$reconciliationId,$bankTransactionId,$amount){
            $recon=BankReconciliation::query()->whereKey($reconciliationId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            $tx=BankTransaction::query()->whereKey($bankTransactionId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if($recon->status!=='in_progress') throw new RuntimeException('Reconciliation is not open.');
            $value=Decimal::normalize($amount);
            if(Decimal::compare($value,'0')<=0) throw new RuntimeException('Matched amount must be positive.');
            $item=BankReconciliationItem::firstOrCreate(['bank_reconciliation_id'=>$recon->id,'bank_transaction_id'=>$tx->id],['matched_amount'=>$value]);
            $tx->update(['status'=>'reconciled']);
            $bookRaw=BankTransaction::query()->where('station_id',$stationId)->where('cash_account_id',$recon->cash_account_id)->whereDate('transaction_date','<=',$recon->statement_date)->selectRaw('COALESCE(SUM(debit - credit), 0) as book_balance')->value('book_balance');
            $book=Decimal::normalize((string)$bookRaw);
            $difference=Decimal::compare((string)$recon->statement_balance,$book)>=0 ? Decimal::sub((string)$recon->statement_balance,$book) : '-'.Decimal::sub($book,(string)$recon->statement_balance);
            $recon->update(['book_balance'=>$book,'difference'=>$difference]);
            return $item;
        });
    }

    public function complete(int $stationId,int $reconciliationId): BankReconciliation
    {
        return DB::transaction(function()use($stationId,$reconciliationId){
            $recon=BankReconciliation::query()->whereKey($reconciliationId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if($recon->status!=='in_progress') throw new RuntimeException('Reconciliation is not in progress.');
            if(Decimal::compare((string)$recon->difference,'0')!==0) throw new RuntimeException('Bank reconciliation cannot complete while a difference remains.');
            $recon->update(['status'=>'completed']);
            return $recon->fresh();
        });
    }
}