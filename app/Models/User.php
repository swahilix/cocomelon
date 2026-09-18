<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_admin', 'role_id'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_admin' => 'boolean'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function mailAccounts(): BelongsToMany
    {
        return $this->belongsToMany(MailAccount::class, 'mail_account_user')
            ->withPivot(['can_read', 'can_send', 'can_delete', 'can_manage'])
            ->withTimestamps();
    }

    public function mailDrafts(): HasMany
    {
        return $this->hasMany(MailDraft::class);
    }

    public function mailOutboxItems(): HasMany
    {
        return $this->hasMany(MailOutbox::class);
    }

    public function mailContacts(): HasMany
    {
        return $this->hasMany(MailContact::class, 'owner_user_id');
    }

    public function mailContactGroups(): HasMany
    {
        return $this->hasMany(MailContactGroup::class, 'owner_user_id');
    }

    public function mailAuditLogs(): HasMany
    {
        return $this->hasMany(MailAuditLog::class);
    }

    public function permissionSlugs(): array
    {
        if (! $this->is_admin) {
            return [];
        }

        // Backward compatibility: before role-based access was introduced,
        // is_admin=true represented a full administrator. Existing production
        // records and older test fixtures may therefore have no role_id yet.
        if (! $this->role_id) {
            return Permission::query()->pluck('slug')->values()->all();
        }

        if ($this->relationLoaded('role') && $this->role?->relationLoaded('permissions')) {
            return $this->role->permissions->pluck('slug')->values()->all();
        }

        return Permission::query()
            ->join('permission_role', 'permission_role.permission_id', '=', 'permissions.id')
            ->where('permission_role.role_id', $this->role_id)
            ->pluck('permissions.slug')
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_admin) {
            return false;
        }

        if (! $this->role_id) {
            return true;
        }

        return in_array($permission, $this->permissionSlugs(), true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if (! $this->is_admin) {
            return false;
        }

        if (! $this->role_id) {
            return ! empty($permissions);
        }

        $granted = $this->permissionSlugs();
        return (bool) array_intersect($permissions, $granted);
    }

    public function hasMailAccountAccess(MailAccount $account, string $capability = 'read'): bool
    {
        if (! $this->is_admin) {
            return false;
        }

        // Scope permission only: the calling policy must still check the
        // action permission such as mail.send or mail.delete separately.
        if ($this->hasPermission('mail.view_all_mailboxes')) {
            return true;
        }

        $column = match ($capability) {
            'send' => 'can_send',
            'delete' => 'can_delete',
            'manage' => 'can_manage',
            default => 'can_read',
        };

        $assignedAccount = $this->relationLoaded('mailAccounts')
            ? $this->mailAccounts->firstWhere('id', $account->getKey())
            : $this->mailAccounts()->whereKey($account->getKey())->first();

        return (bool) ($assignedAccount?->pivot?->{$column} ?? false);
    }

    public function canReadMailAccount(MailAccount $account): bool
    {
        return $this->hasPermission('mail.view') && $this->hasMailAccountAccess($account, 'read');
    }

    public function canSendFromMailAccount(MailAccount $account): bool
    {
        return $this->hasPermission('mail.send') && $this->hasMailAccountAccess($account, 'send');
    }

    public function canDeleteFromMailAccount(MailAccount $account): bool
    {
        return $this->hasPermission('mail.delete') && $this->hasMailAccountAccess($account, 'delete');
    }

    public function canManageMailAccount(MailAccount $account): bool
    {
        return $this->hasPermission('mail.manage_mailboxes') && $this->hasMailAccountAccess($account, 'manage');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }
}
