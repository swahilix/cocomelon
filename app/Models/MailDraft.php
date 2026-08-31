<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_account_id', 'user_id', 'reply_to_message_id', 'forward_of_message_id',
        'subject', 'body_html', 'body_text', 'to_json', 'cc_json', 'bcc_json',
        'scheduled_at', 'last_saved_at',
    ];

    protected function casts(): array
    {
        return [
            'to_json' => 'array',
            'cc_json' => 'array',
            'bcc_json' => 'array',
            'scheduled_at' => 'datetime',
            'last_saved_at' => 'datetime',
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

    public function replyToMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'reply_to_message_id');
    }

    public function forwardOfMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'forward_of_message_id');
    }

    public function outboxItems(): HasMany
    {
        return $this->hasMany(MailOutbox::class, 'draft_id');
    }
}