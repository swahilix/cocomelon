<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use App\Services\PostingGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SensitiveAction
{
    public function __construct(
        private PostingGuard $guard,
        private AuditService $audit,
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        string $action,
        string $subjectType,
        string $table,
        string $idSource,
    ): Response {
        $subjectId = $this->resolveSubjectId($request, $idSource);

        DB::beginTransaction();
        try {
            $before = $subjectId
                ? DB::table($table)->where('id', $subjectId)->lockForUpdate()->first()
                : null;

            $this->guard->claim($request, $action, $subjectType, $subjectId);
            $response = $next($request);

            $newFlash = (array) $request->session()->get('_flash.new', []);
            $hasCurrentErrors = in_array('errors', $newFlash, true);
            if ($response->getStatusCode() >= 400 || $hasCurrentErrors) {
                DB::rollBack();
                return $response;
            }

            $after = $subjectId ? DB::table($table)->where('id', $subjectId)->first() : null;
            $reference = $after->reference ?? $before->reference ?? null;
            $this->audit->record(
                $request,
                $action,
                $subjectType,
                $subjectId,
                $reference,
                $before,
                $after,
                ['route' => $request->route()?->getName()],
            );

            DB::commit();
            return $response;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $exception;
        }
    }

    private function resolveSubjectId(Request $request, string $source): ?int
    {
        if (str_starts_with($source, 'route:')) {
            $value = $request->route(substr($source, 6));
        } elseif (str_starts_with($source, 'input:')) {
            $value = $request->input(substr($source, 6));
        } else {
            $value = null;
        }

        if (is_object($value) && isset($value->id)) {
            $value = $value->id;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
