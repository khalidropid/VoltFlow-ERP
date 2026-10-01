<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditLogger;
use App\Support\StationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuditApiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $response=$next($request);
        if($request->user()){
            app(AuditLogger::class)->record('api.request',null,null,['method'=>$request->method(),'path'=>$request->path(),'status'=>$response->getStatusCode()],app(StationContext::class)->currentId(),$request);
        }
        return $response;
    }
}