<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_message_id', 'remote_attachment_id', 'part_number', 'original_name',
        'mime_type', 'size', 'storage_disk', 'storage_path', 'content_id', 'is_inline',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_inline' => 'boolean',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'mail_message_id');
    }
}