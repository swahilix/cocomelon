<?php

namespace App\Policies;

use App\Models\MailOutbox;
use App\Models\User;

class MailOutboxPolicy
{
    public function view(User $user, MailOutbox $item): bool
    {
        return $user->hasPermission('mail.view')
            && $user->hasMailAccountAccess($item->account, 'read')
            && $this->ownsOrCanViewAll($user, $item);
    }

    public function retry(User $user, MailOutbox $item): bool
    {
        return $user->hasPermission('mail.send')
            && $user->hasMailAccountAccess($item->account, 'send')
            && $this->ownsOrCanViewAll($user, $item);
    }

    public function cancel(User $user, MailOutbox $item): bool
    {
        return $this->retry($user, $item);
    }

    public function edit(User $user, MailOutbox $item): bool
    {
        return $user->hasPermission('mail.compose')
            && $user->hasMailAccountAccess($item->account, 'send')
            && $this->ownsOrCanViewAll($user, $item);
    }

    private function ownsOrCanViewAll(User $user, MailOutbox $item): bool
    {
        return (int) $item->user_id === (int) $user->getKey()
            || $user->hasPermission('mail.view_all_mailboxes');
    }
}
