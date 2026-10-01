<?php

namespace App\Services\Billing;

use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Support\Decimal;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BillingAdjustmentService
{
    public function __construct(private readonly JournalEntryService $journalEntryService) {}
    public function adjustInvoice(int $stationId,int $invoiceId,string $type,string $amount,string $reason,?int $userId=null): InvoiceAdjustment
    {
        return DB::transaction(function()use($stationId,$invoiceId,$type,$amount,$reason,$userId){
            $invoice=Invoice::query()->whereKey($invoiceId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if($invoice->status==='void')throw new RuntimeException('Voided invoices cannot be adjusted.');
            if(!in_array($type,['discount','surcharge','credit','debit'],true))throw new RuntimeException('Invalid invoice adjustment type.');
            $value=Decimal::normalize($amount); if(Decimal::compare($value,'0')<=0)throw new RuntimeException('Adjustment amount must be positive.');
            $adj=InvoiceAdjustment::create(['invoice_id'=>$invoice->id,'type'=>$type,'reason'=>$reason,'amount'=>$value,'created_by'=>$userId]);
            $total=(string)$invoice->total;
            if(in_array($type,['discount','credit'],true)){
                if(Decimal::compare($value,$total)>0)throw new RuntimeException('Credit/discount cannot exceed invoice total.');
                $total=Decimal::sub($total,$value);
            }else{$total=Decimal::add($total,$value);}
            $invoice->update(['total'=>$total,'status'=>Decimal::compare($invoice->paid_amount,$total)>=0?'paid':(Decimal::compare($invoice->paid_amount,'0')>0?'partially_paid':'issued')]);
            $period=app(\App\Services\Accounting\CollectionAccountingService::class)->openPeriodForDate($stationId, now()->format('Y-m-d'));
            $ar=app(\App\Services\Accounting\CollectionAccountingService::class)->postableAccountId($stationId,'1200');
            $account=app(\App\Services\Accounting\CollectionAccountingService::class)->postableAccountId($stationId,in_array($type,['discount','credit'],true)?'4100':'4200');
            $entry=$this->journalEntryService->createAndPost($stationId,$period->id,'ADJ-'.$adj->id,now()->format('Y-m-d'),'Invoice adjustment '.$adj->id,[
                ['account_id'=>$account,'debit'=>in_array($type,['discount','credit'],true)?$value:'0','credit'=>in_array($type,['discount','credit'],true)?'0':$value],
                ['account_id'=>$ar,'debit'=>in_array($type,['discount','credit'],true)?'0':$value,'credit'=>in_array($type,['discount','credit'],true)?$value:'0'],
            ],$userId?\App\Models\User::find($userId):null,'invoice_adjustment',$adj->id);
            $adj->update(['journal_entry_id'=>$entry->id]);
            return $adj;
        });
    }

    public function issueCreditNote(int $stationId,int $customerId,string $uuid,string $number,string $date,string $amount,?int $invoiceId=null,?string $reason=null): CreditNote
    {
        return $this->issueNote(CreditNote::class,$stationId,$customerId,$uuid,$number,$date,$amount,$invoiceId,$reason);
    }

    public function issueDebitNote(int $stationId,int $customerId,string $uuid,string $number,string $date,string $amount,?int $invoiceId=null,?string $reason=null): DebitNote
    {
        return $this->issueNote(DebitNote::class,$stationId,$customerId,$uuid,$number,$date,$amount,$invoiceId,$reason);
    }

    private function issueNote(string $class,int $stationId,int $customerId,string $uuid,string $number,string $date,string $amount,?int $invoiceId,?string $reason)
    {
        return DB::transaction(function()use($class,$stationId,$customerId,$uuid,$number,$date,$amount,$invoiceId,$reason){
            $existing=$class::query()->where('transaction_uuid',$uuid)->first(); if($existing)return $existing;
            if($invoiceId){Invoice::query()->whereKey($invoiceId)->where('station_id',$stationId)->where('customer_id',$customerId)->firstOrFail();}
            $value=Decimal::normalize($amount); if(Decimal::compare($value,'0')<=0)throw new RuntimeException('Note amount must be positive.');
            $note=$class::create(['transaction_uuid'=>$uuid,'station_id'=>$stationId,'customer_id'=>$customerId,'invoice_id'=>$invoiceId,'number'=>$number,'note_date'=>$date,'amount'=>$value,'reason'=>$reason,'status'=>'issued']);
            $period=app(\App\Services\Accounting\CollectionAccountingService::class)->openPeriodForDate($stationId,$date);
            $ar=app(\App\Services\Accounting\CollectionAccountingService::class)->postableAccountId($stationId,'1200');
            $account=app(\App\Services\Accounting\CollectionAccountingService::class)->postableAccountId($stationId,$class===CreditNote::class?'4100':'4200');
            $entry=app(\App\Services\Accounting\JournalEntryService::class)->createAndPost($stationId,$period->id,'NOTE-'.$note->number,$date,($class===CreditNote::class?'Credit':'Debit').' note '.$note->number,[
                ['account_id'=>$account,'debit'=>$class===CreditNote::class?'0':$value,'credit'=>$class===CreditNote::class?$value:'0'],
                ['account_id'=>$ar,'debit'=>$class===CreditNote::class?$value:'0','credit'=>$class===CreditNote::class?'0':$value],
            ],null,$class===CreditNote::class?'credit_note':'debit_note',$note->id);
            $note->update(['journal_entry_id'=>$entry->id]);
            return $note;
        });
    }
}