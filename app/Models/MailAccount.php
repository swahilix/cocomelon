<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MailAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'provider',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password_encrypted',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password_encrypted',
        'from_name', 'reply_to', 'signature_html', 'sync_enabled', 'send_enabled',
        'last_synced_at', 'last_connection_at', 'last_error', 'status',
    ];

    protected $hidden = [
        'imap_password_encrypted',
        'smtp_password_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'imap_port' => 'integer',
            'smtp_port' => 'integer',
            'sync_enabled' => 'boolean',
            'send_enabled' => 'boolean',
            'last_synced_at' => 'datetime',
            'last_connection_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mail_account_user')
            ->withPivot(['can_read', 'can_send', 'can_delete', 'can_manage'])
            ->withTimestamps();
    }

    public function folders(): HasMany
    {
        return $this->hasMany(MailFolder::class);
    }

    public function inboxFolder(): HasOne
    {
        return $this->hasOne(MailFolder::class)->where('type', MailFolder::TYPE_INBOX);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(MailDraft::class);
    }

    public function outboxItems(): HasMany
    {
        return $this->hasMany(MailOutbox::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(MailContact::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(MailAuditLog::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSyncEnabled(Builder $query): Builder
    {
        return $query->where('sync_enabled', true);
    }

    public function scopeSendEnabled(Builder $query): Builder
    {
        return $query->where('send_enabled', true);
    }
}