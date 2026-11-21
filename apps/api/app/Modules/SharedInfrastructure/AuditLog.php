<?php

declare(strict_types=1);

namespace App\Modules\SharedInfrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AuditLog
{
    /** Callers supply field names, state flags and identifiers only. */
    public function record(
        ?string $actorId,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        array $details = [],
    ): void {
        DB::table('audit_logs')->insert([
            'id' => Str::uuid()->toString(),
            'actor_id' => $actorId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'request_id' => RequestContext::id(),
            'details' => json_encode((object) $details, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);
    }
}
