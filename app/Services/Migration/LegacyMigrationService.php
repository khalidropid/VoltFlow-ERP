<?php

namespace App\Services\Migration;

use App\Models\LegacyIdMapping;
use App\Models\IntegrationSource;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LegacyMigrationService
{
    public function dryRun(int $sourceId,string $legacyTable,array $rows,string $entityType,string $legacyKey='id'): array
    {
        $source=IntegrationSource::query()->findOrFail($sourceId);
        $seen=[];$duplicates=[];$missing=[];
        foreach($rows as $row){$id=(string)($row[$legacyKey]??'');if($id===''){$missing[]=$row;continue;}if(isset($seen[$id]))$duplicates[]=$id;$seen[$id]=true;}
        $existing=LegacyIdMapping::query()->where('integration_source_id',$source->id)->where('legacy_table',$legacyTable)->pluck('legacy_id')->map(fn($v)=>(string)$v)->all();
        return ['source'=>$source->code,'table'=>$legacyTable,'rows'=>count($rows),'missing_keys'=>count($missing),'duplicate_keys'=>array_values(array_unique($duplicates)),'already_mapped'=>array_values(array_intersect(array_keys($seen),$existing)),'ready'=>count($missing)===0&&count($duplicates)===0];
    }

    public function map(int $sourceId,string $legacyTable,string $legacyId,string $entityType,int $entityId): LegacyIdMapping
    {
        return DB::transaction(function()use($sourceId,$legacyTable,$legacyId,$entityType,$entityId){
            IntegrationSource::query()->findOrFail($sourceId);
            $existing=LegacyIdMapping::query()->where('integration_source_id',$sourceId)->where('legacy_table',$legacyTable)->where('legacy_id',$legacyId)->first();
            if($existing){if($existing->entity_type!==$entityType||$existing->entity_id!==$entityId)throw new RuntimeException('Legacy ID is already mapped to another entity.');return $existing;}
            return LegacyIdMapping::create(['integration_source_id'=>$sourceId,'legacy_table'=>$legacyTable,'legacy_id'=>$legacyId,'entity_type'=>$entityType,'entity_id'=>$entityId]);
        });
    }
}