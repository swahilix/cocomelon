<?php

namespace App\Policies;

use App\Models\MailAttachment;
use App\Models\User;

class MailAttachmentPolicy
{
    public function view(User $user, MailAttachment $attachment): bool
    {
        return $user->canReadMailAccount($attachment->message->account);
    }
}
