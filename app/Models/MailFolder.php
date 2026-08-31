<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailFolder extends Model
{
    use HasFactory;

    public const TYPE_INBOX = 'inbox';
    public const TYPE_DRAFTS = 'drafts';
    public const TYPE_OUTBOX = 'outbox';
    public const TYPE_SENT = 'sent';
    public const TYPE_TRASH = 'trash';
    public const TYPE_SPAM = 'spam';
    public const TYPE_ARCHIVE = 'archive';
    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'mail_account_id', 'remote_name', 'display_name', 'type', 'delimiter',
        'uid_validity', 'last_uid', 'sync_token', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'uid_validity' => 'integer',
            'last_uid' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeInbox(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_INBOX);
    }
}