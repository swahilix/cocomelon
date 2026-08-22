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
            'before_values' => $this->encode($before),
            'after_values' => $this->encode($after),
            'metadata' => $metadata ? $this->encode($metadata) : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            'created_at' => now(),
        ]);
    }

    private function encode(array|object|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = json_decode(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
        return json_encode($this->redact($normalized), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            if ($this->isSensitiveKey($normalizedKey)) {
                $value[$key] = '[REDACTED]';
                continue;
            }

            $value[$key] = $this->redact($item);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (['password', 'remember_token', 'token', 'secret', 'credential', 'api_key', 'private_key'] as $needle) {
            if ($key === $needle || str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
