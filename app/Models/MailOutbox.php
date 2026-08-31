<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailOutbox extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'mail_outbox';

    protected $fillable = [
        'mail_account_id', 'user_id', 'draft_id', 'status', 'attempts', 'available_at',
        'processing_at', 'sent_at', 'failed_at', 'last_error', 'smtp_message_id', 'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'processing_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'payload_json' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(MailDraft::class, 'draft_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_QUEUED, self::STATUS_SENDING]);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeReadyToSend(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_QUEUED)
            ->where(function (Builder $available): void {
                $available->whereNull('available_at')->orWhere('available_at', '<=', now());
            });
    }
}