<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(
        string $event,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $stationId = null,
        ?Request $request = null
    ): AuditLog {
        $user = $request?->user() ?? auth()->user();
        $user = $user instanceof User ? $user : null;

        return AuditLog::create([
            'user_id' => $user?->id,
            'station_id' => $stationId,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'request_id' => $request?->header('X-Request-ID'),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
