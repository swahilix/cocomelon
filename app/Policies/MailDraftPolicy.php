<?php

namespace App\Policies;

use App\Models\MailAccount;
use App\Models\MailDraft;
use App\Models\User;

class MailDraftPolicy
{
    public function create(User $user, MailAccount $account): bool
    {
        return $user->hasPermission('mail.compose')
            && $user->hasMailAccountAccess($account, 'send');
    }

    public function view(User $user, MailDraft $draft): bool
    {
        return $user->hasPermission('mail.compose')
            && $user->hasMailAccountAccess($draft->account, 'read')
            && $this->ownsOrCanViewAll($user, $draft);
    }

    public function update(User $user, MailDraft $draft): bool
    {
        return $user->hasPermission('mail.compose')
            && $user->hasMailAccountAccess($draft->account, 'send')
            && $this->ownsOrCanViewAll($user, $draft);
    }

    public function delete(User $user, MailDraft $draft): bool
    {
        return $this->update($user, $draft);
    }

    public function send(User $user, MailDraft $draft): bool
    {
        return $user->hasPermission('mail.send')
            && $user->hasMailAccountAccess($draft->account, 'send')
            && $this->ownsOrCanViewAll($user, $draft);
    }

    private function ownsOrCanViewAll(User $user, MailDraft $draft): bool
    {
        return (int) $draft->user_id === (int) $user->getKey()
            || $user->hasPermission('mail.view_all_mailboxes');
    }
}
