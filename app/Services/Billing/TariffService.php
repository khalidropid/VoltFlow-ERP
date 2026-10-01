<?php

namespace App\Services\Billing;

use App\Models\Tariff;
use App\Models\TariffSlab;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TariffService
{
    public function replaceSlabs(int $stationId,int $tariffId,array $slabs): Tariff
    {
        return DB::transaction(function()use($stationId,$tariffId,$slabs){
            $tariff=Tariff::query()->whereKey($tariffId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if($tariff->is_active && $tariff->effective_to===null && empty($slabs)) throw new RuntimeException('Active tariffs must contain at least one slab.');
            usort($slabs,fn($a,$b)=>(int)($a['sort_order']??0)<=>(int)($b['sort_order']??0));
            $previousTo=null;
            foreach($slabs as $index=>$slab){
                $from=Decimal::normalize((string)$slab['from_unit']);
                $to=array_key_exists('to_unit',$slab) && $slab['to_unit']!==null ? Decimal::normalize((string)$slab['to_unit']) : null;
                $rate=Decimal::normalize((string)$slab['rate']);
                if(Decimal::compare($rate,'0')<0) throw new RuntimeException('Tariff rate cannot be negative.');
                if($to!==null && Decimal::compare($to,$from)<=0) throw new RuntimeException('Tariff slab upper bound must exceed lower bound.');
                if($previousTo!==null && Decimal::compare($from,$previousTo)!==0) throw new RuntimeException('Tariff slabs must be contiguous and non-overlapping.');
                $previousTo=$to;
                if($to===null && $index!==count($slabs)-1) throw new RuntimeException('An open-ended slab must be the final slab.');
            }
            TariffSlab::query()->where('tariff_id',$tariff->id)->delete();
            foreach($slabs as $index=>$slab){
                TariffSlab::create(['tariff_id'=>$tariff->id,'from_unit'=>Decimal::normalize((string)$slab['from_unit']),'to_unit'=>isset($slab['to_unit'])&&$slab['to_unit']!==null?Decimal::normalize((string)$slab['to_unit']):null,'rate'=>Decimal::normalize((string)$slab['rate']),'sort_order'=>$index+1]);
            }
            return $tariff->fresh('slabs');
        });
    }
}