<?php

namespace App\Services\Reconciliation;

use App\Models\BankTransaction;
use App\Models\CashAccount;
use App\Models\CollectorAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\CollectionSettlement;
use App\Models\FuelStockMovement;
use App\Models\WarehouseStock;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

final class StationReconciliationService
{
    public function billing(int $stationId): array
    {
        $invoices=Invoice::query()->where('station_id',$stationId)->where('status','!=','void')->selectRaw('COALESCE(SUM(total),0) total')->value('total');
        $invoiceJournals=DB::table('journal_entry_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')->where('j.station_id',$stationId)->where('j.source_type','invoice')->where('j.status','posted')->selectRaw('COALESCE(SUM(l.debit),0) total')->value('total');
        return $this->result((string)$invoices,(string)$invoiceJournals);
    }

    public function collections(int $stationId): array
    {
        $payments=Payment::query()->where('station_id',$stationId)->where('status','posted')->selectRaw('COALESCE(SUM(amount),0) total')->value('total');
        $allocated=DB::table('payment_allocations as a')->join('payments as p','p.id','=','a.payment_id')->where('p.station_id',$stationId)->where('p.status','posted')->selectRaw('COALESCE(SUM(a.amount),0) total')->value('total');
        return $this->result((string)$payments,(string)$allocated);
    }

    public function cash(int $stationId,int $cashAccountId): array
    {
        $cash=CashAccount::query()->whereKey($cashAccountId)->where('station_id',$stationId)->firstOrFail();
        $movements=DB::table('cash_movements')->where('station_id',$stationId)->where('cash_account_id',$cashAccountId)->selectRaw("COALESCE(SUM(CASE WHEN movement_type IN ('deposit','transfer_in') THEN amount ELSE -amount END),0) total")->value('total');
        return ['book_balance'=>(string)$cash->balance,'expected_balance'=>Decimal::add((string)$cash->opening_balance,Decimal::normalize((string)$movements)),'difference'=>$this->signedDifference((string)$cash->balance,Decimal::add((string)$cash->opening_balance,Decimal::normalize((string)$movements)))];
    }

    public function collectorCustody(int $stationId,int $collectorAccountId): array
    {
        $account=CollectorAccount::query()->whereKey($collectorAccountId)->where('station_id',$stationId)->firstOrFail();
        $payments=Payment::query()->where('station_id',$stationId)->where('collector_id',$account->collector_id)->where('status','posted')->selectRaw('COALESCE(SUM(amount),0) total')->value('total');
        $settlements=CollectionSettlement::query()->where('station_id',$stationId)->where('collector_account_id',$account->id)->where('status','posted')->selectRaw('COALESCE(SUM(amount),0) total')->value('total');
        $expected=Decimal::add((string)$account->opening_balance,Decimal::sub(Decimal::normalize((string)$payments),Decimal::normalize((string)$settlements)));
        return ['account_balance'=>(string)$account->balance,'expected_balance'=>$expected,'difference'=>$this->signedDifference((string)$account->balance,$expected)];
    }

    public function fuel(int $stationId,int $fuelTankId): array
    {
        $net=DB::table('fuel_stock_movements')->where('station_id',$stationId)->where('fuel_tank_id',$fuelTankId)->selectRaw("COALESCE(SUM(CASE WHEN movement_type='receipt' THEN quantity WHEN movement_type='issue' THEN -quantity ELSE quantity END),0) total")->value('total');
        $tank=DB::table('fuel_tanks')->where('id',$fuelTankId)->where('station_id',$stationId)->value('current_quantity');
        $opening=DB::table('fuel_tanks')->where('id',$fuelTankId)->value('opening_quantity');
        $expected=Decimal::add((string)$opening,Decimal::normalize((string)$net));
        return ['tank_balance'=>(string)$tank,'expected_balance'=>$expected,'difference'=>$this->signedDifference((string)$tank,$expected)];
    }

    private function signedDifference(string $actual,string $expected): string { return Decimal::compare($actual,$expected)>=0 ? Decimal::sub($actual,$expected) : '-'.Decimal::sub($expected,$actual); }

    private function result(string $expected,string $actual): array
    {
        $expected=Decimal::normalize($expected); $actual=Decimal::normalize($actual);
        return ['expected'=>$expected,'actual'=>$actual,'difference'=>$this->signedDifference($expected,$actual),'balanced'=>Decimal::compare($expected,$actual)===0];
    }
}