<?php

namespace App\Services\Reconciliation;

use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FullReconciliationService
{
    public function inventory(int $stationId): array
    {
        $rows=DB::table('warehouse_stocks')->where('station_id',$stationId)->select('id','opening_quantity','quantity')->get();
        $items=[]; foreach($rows as $r){$mov=DB::table('inventory_stock_movements')->where('warehouse_stock_id',$r->id)->selectRaw("COALESCE(SUM(CASE WHEN movement_type IN ('receipt','adjustment_in','transfer_in') THEN quantity ELSE 0 END),0) in_qty, COALESCE(SUM(CASE WHEN movement_type IN ('issue','adjustment_out','transfer_out') THEN quantity ELSE 0 END),0) out_qty")->first();$expected=Decimal::add((string)$r->opening_quantity,Decimal::sub((string)$mov->in_qty,(string)$mov->out_qty));$items[]=['stock_id'=>$r->id,'expected'=>$expected,'actual'=>(string)$r->quantity,'difference'=>$this->signed($expected,(string)$r->quantity),'balanced'=>Decimal::compare($expected,(string)$r->quantity)===0];} return $items;
    }

    public function procurement(int $stationId): array
    {
        $row=DB::table('supplier_invoices')->where('station_id',$stationId)->where('status','posted')->selectRaw('COALESCE(SUM(total_amount),0) total')->first();
        $posted=DB::table('journal_entries')->where('station_id',$stationId)->where('source_type','supplier_invoice')->where('status','posted')->selectRaw('COALESCE(SUM(total_debit),0) debit, COALESCE(SUM(total_credit),0) credit')->first();
        $d=$this->signed((string)$posted->debit,(string)$posted->credit);
        return ['supplier_invoices'=>(string)$row->total,'journal_difference'=>$d,'balanced'=>$d==='0.0000'];
    }

    public function payroll(int $stationId,int $periodId): array
    {
        $slips=DB::table('payroll_slips')->where('payroll_run_id',$periodId)->where('status','posted')->selectRaw('COALESCE(SUM(net_amount),0) net')->first();
        return ['posted_net'=>(string)$slips->net];
    }

    public function generalLedger(int $stationId,int $periodId): array
    {
        $row=DB::table('journal_entry_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')->where('j.station_id',$stationId)->where('j.fiscal_period_id',$periodId)->where('j.status','posted')->selectRaw('COALESCE(SUM(l.debit),0) debit,COALESCE(SUM(l.credit),0) credit')->first();
        $difference=$this->signed((string)$row->debit,(string)$row->credit); return ['debit'=>(string)$row->debit,'credit'=>(string)$row->credit,'difference'=>$difference,'balanced'=>$difference==='0.0000'];
    }

    private function signed(string $a,string $b): string
    {
        return Decimal::compare($a,$b)>=0?Decimal::sub($a,$b):'-'.Decimal::sub($b,$a);
    }
}