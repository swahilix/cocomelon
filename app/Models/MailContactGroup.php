<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MailContactGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'owner_user_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(
            MailContact::class,
            'mail_contact_group_contact',
            'mail_contact_group_id',
            'mail_contact_id'
        )->withTimestamps();
    }
}