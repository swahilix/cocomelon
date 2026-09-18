<?php

namespace App\Services\Mail;

use App\Exceptions\MailCredentialException;
use App\Models\MailAccount;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use InvalidArgumentException;

class MailCredentialVault
{
    public function __construct(private readonly Encrypter $encrypter)
    {
    }

    public function encrypt(string $credential): string
    {
        if ($credential === '') {
            throw new InvalidArgumentException('Mail credentials cannot be empty.');
        }

        return $this->encrypter->encryptString($credential);
    }

    public function decrypt(?string $encryptedCredential, string $transport): string
    {
        if ($encryptedCredential === null || $encryptedCredential === '') {
            throw MailCredentialException::missing($transport);
        }

        try {
            return $this->encrypter->decryptString($encryptedCredential);
        } catch (DecryptException $exception) {
            throw MailCredentialException::invalid($transport, $exception);
        }
    }

    public function imapPassword(MailAccount $account): string
    {
        return $this->decrypt(
            $account->getAttributes()['imap_password_encrypted'] ?? null,
            'IMAP',
        );
    }

    public function smtpPassword(MailAccount $account): string
    {
        return $this->decrypt(
            $account->getAttributes()['smtp_password_encrypted'] ?? null,
            'SMTP',
        );
    }
}
