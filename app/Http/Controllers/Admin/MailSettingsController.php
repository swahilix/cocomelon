<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MailSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->hasAnyPermission(['mail.manage_mailboxes', 'mail.manage_settings']), 403);

        $accounts = MailAccount::query()
            ->assignedTo($user, 'manage')
            ->with(['users:id,name,email,role_id'])
            ->orderBy('email')
            ->get()
            ->map(function (MailAccount $account) use ($user): array {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'email' => $account->email,
                    'provider' => $account->provider,
                    'imap_host' => $account->imap_host,
                    'imap_port' => $account->imap_port,
                    'imap_encryption' => $account->imap_encryption,
                    'imap_username' => $account->imap_username,
                    'imap_password_set' => filled($account->getRawOriginal('imap_password_encrypted')),
                    'smtp_host' => $account->smtp_host,
                    'smtp_port' => $account->smtp_port,
                    'smtp_encryption' => $account->smtp_encryption,
                    'smtp_username' => $account->smtp_username,
                    'smtp_password_set' => filled($account->getRawOriginal('smtp_password_encrypted')),
                    'from_name' => $account->from_name,
                    'reply_to' => $account->reply_to,
                    'signature_html' => $account->signature_html,
                    'sync_enabled' => $account->sync_enabled,
                    'send_enabled' => $account->send_enabled,
                    'status' => $account->status,
                    'last_synced_at' => $account->last_synced_at,
                    'last_connection_at' => $account->last_connection_at,
                    'last_error' => $account->last_error,
                    'assignments' => $account->users->map(fn (User $member): array => [
                        'user_id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'can_read' => (bool) $member->pivot->can_read,
                        'can_send' => (bool) $member->pivot->can_send,
                        'can_delete' => (bool) $member->pivot->can_delete,
                        'can_manage' => (bool) $member->pivot->can_manage,
                    ])->values(),
                    'can_edit_settings' => Gate::forUser($user)->allows('manageSettings', $account),
                    'can_assign_users' => Gate::forUser($user)->allows('assignUsers', $account),
                    'can_delete' => Gate::forUser($user)->allows('delete', $account),
                ];
            });

        $canManageMailboxes = $user->hasPermission('mail.manage_mailboxes');

        return Inertia::render('Admin/Mail/Settings', [
            'accounts' => $accounts,
            'staff' => $canManageMailboxes
                ? User::query()
                    ->with('role:id,name')
                    ->where('is_admin', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'role_id'])
                : [],
            'can' => [
                'create' => $canManageMailboxes,
                'manage_mailboxes' => $canManageMailboxes,
                'manage_settings' => $user->hasPermission('mail.manage_settings'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', MailAccount::class);
        $validated = $this->validateMailbox($request, true);

        DB::transaction(function () use ($request, $validated): void {
            $account = MailAccount::query()->create($this->mailboxPayload($validated, $request, true));

            $account->users()->syncWithoutDetaching([
                $request->user()->getKey() => [
                    'can_read' => true,
                    'can_send' => true,
                    'can_delete' => true,
                    'can_manage' => true,
                ],
            ]);
        });

        return back()->with('success', 'Business mailbox created.');
    }

    public function update(Request $request, MailAccount $mailAccount): RedirectResponse
    {
        Gate::authorize('manageSettings', $mailAccount);
        $validated = $this->validateMailbox($request, false, $mailAccount);
        $mailAccount->update($this->mailboxPayload($validated, $request, false));

        return back()->with('success', 'Mailbox settings updated.');
    }

    public function assignUsers(Request $request, MailAccount $mailAccount): RedirectResponse
    {
        Gate::authorize('assignUsers', $mailAccount);

        $validated = $request->validate([
            'assignments' => ['array', 'max:100'],
            'assignments.*.user_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_admin', true)),
            ],
            'assignments.*.can_read' => ['required', 'boolean'],
            'assignments.*.can_send' => ['required', 'boolean'],
            'assignments.*.can_delete' => ['required', 'boolean'],
            'assignments.*.can_manage' => ['required', 'boolean'],
        ]);

        $assignments = collect($validated['assignments'] ?? []);
        $actor = $request->user();

        if (! $actor->hasPermission('mail.view_all_mailboxes')) {
            $self = $assignments->firstWhere('user_id', $actor->getKey());
            if (! $self || ! (bool) ($self['can_manage'] ?? false)) {
                throw ValidationException::withMessages([
                    'assignments' => 'Keep your own Manage access on this mailbox to avoid locking yourself out.',
                ]);
            }
        }

        $sync = $assignments->mapWithKeys(function (array $assignment): array {
            $canSend = (bool) $assignment['can_send'];
            $canDelete = (bool) $assignment['can_delete'];
            $canManage = (bool) $assignment['can_manage'];
            $canRead = (bool) $assignment['can_read'] || $canSend || $canDelete || $canManage;

            return [(int) $assignment['user_id'] => [
                'can_read' => $canRead,
                'can_send' => $canSend,
                'can_delete' => $canDelete,
                'can_manage' => $canManage,
            ]];
        })->all();

        $mailAccount->users()->sync($sync);

        return back()->with('success', 'Mailbox staff access updated.');
    }

    public function destroy(MailAccount $mailAccount): RedirectResponse
    {
        Gate::authorize('delete', $mailAccount);

        $hasBusinessData = $mailAccount->messages()->exists()
            || $mailAccount->drafts()->exists()
            || $mailAccount->outboxItems()->exists();

        if ($hasBusinessData) {
            return back()->withErrors([
                'mailbox' => 'This mailbox already contains mail data. Disable it instead of deleting it.',
            ]);
        }

        $mailAccount->delete();

        return back()->with('success', 'Mailbox configuration removed.');
    }

    private function validateMailbox(Request $request, bool $creating, ?MailAccount $account = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('mail_accounts', 'email')->ignore($account?->id)],
            'provider' => ['nullable', 'string', 'max:80'],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'between:1,65535'],
            'imap_encryption' => ['required', Rule::in(['ssl', 'tls', 'starttls', 'none'])],
            'imap_username' => ['required', 'string', 'max:191'],
            'imap_password' => [$creating ? 'required' : 'nullable', 'string', 'max:4096'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', Rule::in(['ssl', 'tls', 'starttls', 'none'])],
            'smtp_username' => ['required', 'string', 'max:191'],
            'smtp_password' => [$creating ? 'required' : 'nullable', 'string', 'max:4096'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'reply_to' => ['nullable', 'email:rfc', 'max:191'],
            'signature_html' => ['nullable', 'string', 'max:20000'],
            'sync_enabled' => ['required', 'boolean'],
            'send_enabled' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['active', 'disabled'])],
        ]);
    }

    private function mailboxPayload(array $validated, Request $request, bool $creating): array
    {
        $payload = [
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'provider' => filled($validated['provider'] ?? null) ? trim($validated['provider']) : null,
            'imap_host' => trim($validated['imap_host']),
            'imap_port' => (int) $validated['imap_port'],
            'imap_encryption' => $validated['imap_encryption'],
            'imap_username' => trim($validated['imap_username']),
            'smtp_host' => trim($validated['smtp_host']),
            'smtp_port' => (int) $validated['smtp_port'],
            'smtp_encryption' => $validated['smtp_encryption'],
            'smtp_username' => trim($validated['smtp_username']),
            'from_name' => filled($validated['from_name'] ?? null) ? trim($validated['from_name']) : null,
            'reply_to' => filled($validated['reply_to'] ?? null) ? strtolower(trim($validated['reply_to'])) : null,
            'signature_html' => filled($validated['signature_html'] ?? null) ? trim($validated['signature_html']) : null,
            'sync_enabled' => $request->boolean('sync_enabled'),
            'send_enabled' => $request->boolean('send_enabled'),
            'status' => $validated['status'],
        ];

        if ($payload['status'] === 'disabled') {
            $payload['sync_enabled'] = false;
            $payload['send_enabled'] = false;
        }

        if ($creating || filled($validated['imap_password'] ?? null)) {
            $payload['imap_password_encrypted'] = Crypt::encryptString((string) $validated['imap_password']);
        }

        if ($creating || filled($validated['smtp_password'] ?? null)) {
            $payload['smtp_password_encrypted'] = Crypt::encryptString((string) $validated['smtp_password']);
        }

        return $payload;
    }
}
