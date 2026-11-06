<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\DataResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Administrators only: back up and then clear sales, orders, stock,
 * purchases, expenses, installments and payments.
 */
class DataResetController extends Controller
{
    /** A backup made by the same administrator within this many minutes is required. */
    private const BACKUP_VALID_MINUTES = 60;

    public function show(Request $request, DataResetService $reset): Response
    {
        $this->authorizeAdministrator($request);

        return Inertia::render('Admin/System/DataReset', [
            'preview' => $reset->preview(),
            'backups' => DataResetService::backups(),
            'recentBackup' => $this->recentBackup($request),
            'backupValidMinutes' => self::BACKUP_VALID_MINUTES,
        ]);
    }

    public function backup(Request $request, DataResetService $reset, AuditService $audit): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        @set_time_limit(300);

        try {
            $name = $reset->backup($request->user()?->id);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The backup could not be created: '.$exception->getMessage());
        }

        Cache::put($this->backupKey($request), $name, now()->addMinutes(self::BACKUP_VALID_MINUTES));
        $audit->record($request, 'system.data_backup_created', 'system', null, $name, null, null, ['file' => $name]);

        return back()->with('success', 'Backup created. Download it and keep it safe before resetting.');
    }

    public function download(Request $request, string $file): BinaryFileResponse
    {
        $this->authorizeAdministrator($request);

        abort_unless(preg_match('/^data-reset-backup-[0-9-]+\.json$/', $file) === 1, 404);
        $path = DataResetService::backupDirectory().DIRECTORY_SEPARATOR.$file;
        abort_unless(File::exists($path), 404);

        return response()->download($path, $file, ['Content-Type' => 'application/json']);
    }

    public function reset(Request $request, DataResetService $reset, AuditService $audit): RedirectResponse
    {
        $this->authorizeAdministrator($request);

        $validated = $request->validate([
            'confirmation' => ['required', 'string'],
            'password' => ['required', 'string'],
            'reset_transactions' => ['boolean'],
            'empty_trash' => ['boolean'],
        ]);

        $resetTransactions = (bool) ($validated['reset_transactions'] ?? true);
        $emptyTrash = (bool) ($validated['empty_trash'] ?? false);
        if (! $resetTransactions && ! $emptyTrash) {
            throw ValidationException::withMessages(['confirmation' => 'Choose what to reset: transactions, the Trash, or both.']);
        }

        if (trim($validated['confirmation']) !== 'RESET') {
            throw ValidationException::withMessages(['confirmation' => 'Type RESET in capital letters to confirm.']);
        }

        if (! Hash::check($validated['password'], (string) $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'That password is not correct.']);
        }

        $backup = $this->recentBackup($request);
        if (! $backup) {
            throw ValidationException::withMessages(['confirmation' => 'Create a backup first (within the last '.self::BACKUP_VALID_MINUTES.' minutes).']);
        }

        $before = $reset->preview();
        @set_time_limit(300);

        try {
            $result = $reset->run($resetTransactions, $emptyTrash);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The reset failed and nothing was changed: '.$exception->getMessage());
        }

        Cache::forget($this->backupKey($request));
        $audit->record($request, $resetTransactions ? 'system.data_reset' : 'system.trash_emptied', 'system', null, 'Data reset', null, null, [
            'backup' => $backup,
            'reset_transactions' => $resetTransactions,
            'empty_trash' => $emptyTrash,
            'deleted' => $result['deleted'],
            'trash' => $result['trash'],
            'reset' => $resetTransactions ? $before['reset'] : null,
        ]);

        $messages = [];
        if ($resetTransactions) {
            $messages[] = 'All sales, orders, stock, purchases, expenses, installments and payments were cleared.';
        }
        if ($emptyTrash) {
            $deletedItems = collect($result['trash'])->sum('deleted');
            $kept = collect($result['trash'])->sum('kept');
            $messages[] = "Trash emptied: {$deletedItems} item".($deletedItems === 1 ? '' : 's').' permanently deleted'
                .($kept ? ", {$kept} kept because ".($kept === 1 ? 'it is' : 'they are').' still used (for example by a warranty or another product).' : '.');
        }

        return redirect()->route('admin.system.reset.show')->with('success', implode(' ', $messages));
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user()?->isAdministrator(), 403, 'Only administrators can reset data.');
    }

    private function recentBackup(Request $request): ?string
    {
        $name = Cache::get($this->backupKey($request));

        return $name && File::exists(DataResetService::backupDirectory().DIRECTORY_SEPARATOR.$name) ? $name : null;
    }

    private function backupKey(Request $request): string
    {
        return 'data-reset:backup:'.$request->user()?->id;
    }
}
