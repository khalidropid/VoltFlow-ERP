<?php

namespace App\Services\Production;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProductionReadinessService
{
    public function check(): array
    {
        $checks=[];
        try { DB::select('select 1'); $checks['database']=true; } catch (\Throwable $e) { $checks['database']=false; }
        $checks['app_key']=filled(config('app.key'));
        $checks['debug_disabled']=config('app.debug')===false;
        $checks['cache_ready']=Schema::hasTable('cache');
        $checks['jobs_ready']=Schema::hasTable('jobs');
        $checks['audit_ready']=Schema::hasTable('audit_logs');
        $checks['integration_ready']=Schema::hasTable('integration_events');
        $checks['all_passed']=!in_array(false,$checks,true);
        return $checks;
    }
}