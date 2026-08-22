<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public function record(
        Request $request,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $reference = null,
        array|object|null $before = null,
        array|object|null $after = null,
        array $metadata = [],
    ): int {
        return DB::table('audit_logs')->insertGetId([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reference' => $reference,
            'before_values' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'after_values' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            'created_at' => now(),
        ]);
    }
}
