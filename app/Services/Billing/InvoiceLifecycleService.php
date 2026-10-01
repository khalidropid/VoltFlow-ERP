<?php

namespace App\Services\Billing;

use App\Models\FiscalPeriod;
use App\Models\Invoice;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class InvoiceLifecycleService
{
    public function __construct(private readonly JournalEntryService $journalEntryService) {}

    public function void(int $stationId,int $invoiceId,string $voidDate,?int $actorId=null): Invoice
    {
        return DB::transaction(function()use($stationId,$invoiceId,$voidDate,$actorId){
            $invoice=Invoice::query()->whereKey($invoiceId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if($invoice->status==='void')return $invoice;
            if(!$invoice->journal_entry_id)throw new RuntimeException('Only posted invoices can be voided.');
            $period=FiscalPeriod::query()->where('station_id',$stationId)->where('status','open')
                ->whereDate('starts_on','<=',$voidDate)->whereDate('ends_on','>=',$voidDate)->firstOrFail();
            $original=$invoice->journalEntry()->with('lines')->firstOrFail();
            if($original->station_id!==$stationId||$original->status!=='posted')throw new RuntimeException('Original invoice journal is not postable for reversal.');
            if($original->reversals()->exists())throw new RuntimeException('Invoice journal has already been reversed.');
            $lines=$original->lines->map(fn($line)=>[
                'account_id'=>$line->account_id,'debit'=>(string)$line->credit,'credit'=>(string)$line->debit,
                'description'=>'Reverse invoice '.$invoice->number,
                'customer_id'=>$line->customer_id,'supplier_id'=>$line->supplier_id,'employee_id'=>$line->employee_id,'generator_id'=>$line->generator_id,
            ])->all();
            $entry=$this->journalEntryService->createAndPost(
                $stationId,$period->id,'REV-INV-'.$invoice->number,$voidDate,'Reverse invoice '.$invoice->number,
                $lines,$actorId?\App\Models\User::find($actorId):null,'invoice_reversal',$invoice->id
            );
            $original->update(['status'=>'reversed']);
            $invoice->update(['status'=>'void']);
            return $invoice->fresh(['journalEntry','items']);
        });
    }
}