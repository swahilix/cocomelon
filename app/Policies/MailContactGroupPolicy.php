<?php

namespace App\Policies;

use App\Models\MailContactGroup;
use App\Models\User;

class MailContactGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('mail.manage_contacts');
    }

    public function view(User $user, MailContactGroup $group): bool
    {
        return $user->hasPermission('mail.manage_contacts')
            && (
                (int) $group->owner_user_id === (int) $user->getKey()
                || $user->hasPermission('mail.view_all_mailboxes')
            );
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('mail.manage_contacts');
    }

    public function update(User $user, MailContactGroup $group): bool
    {
        return $this->view($user, $group);
    }

    public function delete(User $user, MailContactGroup $group): bool
    {
        return $this->view($user, $group);
    }
}
