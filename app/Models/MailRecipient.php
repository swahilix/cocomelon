<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailRecipient extends Model
{
    use HasFactory;

    public const TYPE_TO = 'to';
    public const TYPE_CC = 'cc';
    public const TYPE_BCC = 'bcc';

    protected $fillable = [
        'mail_message_id', 'type', 'name', 'email',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'mail_message_id');
    }
}