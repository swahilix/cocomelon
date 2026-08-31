<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_account_id', 'mail_folder_id', 'remote_uid', 'message_id', 'in_reply_to',
        'thread_key', 'from_name', 'from_email', 'reply_to_email', 'subject', 'preview_text',
        'body_text', 'body_html', 'received_at', 'sent_at', 'size', 'has_attachments',
        'is_read', 'is_starred', 'is_important', 'is_draft', 'is_deleted', 'headers_json', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'remote_uid' => 'integer',
            'received_at' => 'datetime',
            'sent_at' => 'datetime',
            'size' => 'integer',
            'has_attachments' => 'boolean',
            'is_read' => 'boolean',
            'is_starred' => 'boolean',
            'is_important' => 'boolean',
            'is_draft' => 'boolean',
            'is_deleted' => 'boolean',
            'headers_json' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MailFolder::class, 'mail_folder_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class);
    }

    public function toRecipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class)->where('type', MailRecipient::TYPE_TO);
    }

    public function ccRecipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class)->where('type', MailRecipient::TYPE_CC);
    }

    public function bccRecipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class)->where('type', MailRecipient::TYPE_BCC);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailAttachment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(MailAuditLog::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeStarred(Builder $query): Builder
    {
        return $query->where('is_starred', true);
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', false);
    }

    public function scopeInThread(Builder $query, string $threadKey): Builder
    {
        return $query->where('thread_key', $threadKey);
    }
}