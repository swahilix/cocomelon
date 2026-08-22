<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PostingGuard
{
    public function claim(Request $request, string $action, ?string $subjectType = null, ?int $subjectId = null): string
    {
        $explicit = trim((string) ($request->input('_operation_id') ?: $request->header('X-Idempotency-Key')));
        $key = $explicit !== '' ? $explicit : $this->fallbackKey($request, $action);
        $hash = hash('sha256', json_encode($request->except(['_token', '_operation_id']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            DB::table('posting_guards')->insert([
                'user_id' => $request->user()?->id,
                'action' => $action,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw new RuntimeException('This action was already submitted. Refresh the page before trying again.');
            }
            throw $exception;
        }

        return $key;
    }

    private function fallbackKey(Request $request, string $action): string
    {
        $fingerprint = hash('sha256', implode('|', [
            (string) $request->user()?->id,
            $action,
            $request->method(),
            $request->path(),
            json_encode($request->except(['_token']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]));

        // Ten-second windows protect double-clicks/retries without permanently blocking
        // a legitimate later action with identical values.
        return 'auto-'.floor(now()->timestamp / 10).'-'.$fingerprint;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique')
            || str_contains(strtolower($exception->getMessage()), 'duplicate');
    }
}
