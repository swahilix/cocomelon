<?php

namespace App\Policies;

use App\Models\MailContact;
use App\Models\User;

class MailContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('mail.manage_contacts');
    }

    public function view(User $user, MailContact $contact): bool
    {
        if (! $user->hasPermission('mail.manage_contacts')) {
            return false;
        }

        if ((int) $contact->owner_user_id === (int) $user->getKey()) {
            return true;
        }

        if ($contact->mail_account_id) {
            return $user->hasMailAccountAccess($contact->account, 'read');
        }

        return $user->hasPermission('mail.view_all_mailboxes');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('mail.manage_contacts');
    }

    public function update(User $user, MailContact $contact): bool
    {
        return $this->view($user, $contact);
    }

    public function delete(User $user, MailContact $contact): bool
    {
        return $this->update($user, $contact);
    }
}
