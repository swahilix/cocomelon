<?php

namespace App\Policies;

use App\Models\MailFolder;
use App\Models\User;

class MailFolderPolicy
{
    public function view(User $user, MailFolder $folder): bool
    {
        return $user->canReadMailAccount($folder->account);
    }
}
