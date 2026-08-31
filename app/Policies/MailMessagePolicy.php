<?php

namespace App\Policies;

use App\Models\MailMessage;
use App\Models\User;

class MailMessagePolicy
{
    public function view(User $user, MailMessage $message): bool
    {
        return $user->canReadMailAccount($message->account);
    }

    public function updateFlags(User $user, MailMessage $message): bool
    {
        return $this->view($user, $message);
    }

    public function reply(User $user, MailMessage $message): bool
    {
        return $user->hasPermission('mail.reply')
            && $user->hasMailAccountAccess($message->account, 'send');
    }

    public function forward(User $user, MailMessage $message): bool
    {
        return $user->hasPermission('mail.forward')
            && $user->hasMailAccountAccess($message->account, 'send');
    }

    public function delete(User $user, MailMessage $message): bool
    {
        return $user->canDeleteFromMailAccount($message->account);
    }

    public function restore(User $user, MailMessage $message): bool
    {
        return $user->hasPermission('mail.restore')
            && $user->hasMailAccountAccess($message->account, 'delete');
    }

    public function forceDelete(User $user, MailMessage $message): bool
    {
        return $this->delete($user, $message);
    }
}
