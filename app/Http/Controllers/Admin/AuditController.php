<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $query = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->whereBetween('audit_logs.created_at', [$from, $to])
            ->when(! empty($validated['user']), fn ($q) => $q->where('audit_logs.user_id', $validated['user']))
            ->when(! empty($validated['action']), fn ($q) => $q->where('audit_logs.action', $validated['action']))
            ->when(! empty($validated['search']), function ($q) use ($validated): void {
                $search = '%'.trim($validated['search']).'%';
                $q->where(function ($inner) use ($search): void {
                    $inner->where('audit_logs.reference', 'like', $search)
                        ->orWhere('audit_logs.subject_type', 'like', $search)
                        ->orWhere('audit_logs.ip_address', 'like', $search)
                        ->orWhere('users.name', 'like', $search)
                        ->orWhere('users.email', 'like', $search);
                });
            });

        $logs = $query
            ->select('audit_logs.*', 'users.name as user_name', 'users.email as user_email')
            ->latest('audit_logs.id')
            ->limit(500)
            ->get()
            ->map(function ($log): object {
                $log->before_values = $log->before_values ? json_decode($log->before_values, true) : null;
                $log->after_values = $log->after_values ? json_decode($log->after_values, true) : null;
                $log->metadata = $log->metadata ? json_decode($log->metadata, true) : null;
                return $log;
            });

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $logs,
            'users' => DB::table('users')->where('is_admin', true)->orderBy('name')->get(['id', 'name', 'email']),
            'actions' => DB::table('audit_logs')->distinct()->orderBy('action')->pluck('action'),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'user' => $validated['user'] ?? '',
                'action' => $validated['action'] ?? '',
                'search' => $validated['search'] ?? '',
            ],
        ]);
    }
}
