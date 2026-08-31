<?php

namespace App\Policies;

use App\Models\MailAccount;
use App\Models\User;

class MailAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('mail.view');
    }

    public function view(User $user, MailAccount $account): bool
    {
        return $user->canReadMailAccount($account);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('mail.manage_mailboxes');
    }

    public function update(User $user, MailAccount $account): bool
    {
        return $user->hasPermission('mail.manage_mailboxes')
            && $user->hasMailAccountAccess($account, 'manage');
    }

    public function delete(User $user, MailAccount $account): bool
    {
        return $this->update($user, $account);
    }

    public function manageSettings(User $user, MailAccount $account): bool
    {
        return $user->hasPermission('mail.manage_settings')
            && $user->hasMailAccountAccess($account, 'manage');
    }

    public function compose(User $user, MailAccount $account): bool
    {
        return $user->hasPermission('mail.compose')
            && $user->hasMailAccountAccess($account, 'send');
    }

    public function send(User $user, MailAccount $account): bool
    {
        return $user->canSendFromMailAccount($account);
    }

    public function assignUsers(User $user, MailAccount $account): bool
    {
        return $this->update($user, $account);
    }
}
