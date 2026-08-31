<?php

namespace Tests\Unit;

use App\Exceptions\MailCredentialException;
use App\Models\MailAccount;
use App\Services\Mail\MailCredentialVault;
use Tests\TestCase;

class MailCredentialVaultTest extends TestCase
{
    public function test_it_encrypts_and_decrypts_mail_credentials(): void
    {
        $vault = app(MailCredentialVault::class);
        $plain = 'mail-password-123!';
        $encrypted = $vault->encrypt($plain);

        $this->assertNotSame($plain, $encrypted);
        $this->assertSame($plain, $vault->decrypt($encrypted, 'IMAP'));
    }

    public function test_it_reads_transport_credentials_from_a_mail_account(): void
    {
        $vault = app(MailCredentialVault::class);
        $account = new MailAccount();
        $account->setRawAttributes([
            'imap_password_encrypted' => $vault->encrypt('imap-secret'),
            'smtp_password_encrypted' => $vault->encrypt('smtp-secret'),
        ]);

        $this->assertTrue($account->hasStoredImapPassword());
        $this->assertTrue($account->hasStoredSmtpPassword());
        $this->assertSame('imap-secret', $vault->imapPassword($account));
        $this->assertSame('smtp-secret', $vault->smtpPassword($account));
    }

    public function test_corrupted_ciphertext_fails_without_exposing_the_ciphertext(): void
    {
        $vault = app(MailCredentialVault::class);
        $ciphertext = 'definitely-not-valid-encrypted-data';

        try {
            $vault->decrypt($ciphertext, 'SMTP');
            $this->fail('Expected credential decryption to fail.');
        } catch (MailCredentialException $exception) {
            $this->assertStringNotContainsString($ciphertext, $exception->getMessage());
            $this->assertStringContainsString('SMTP', $exception->getMessage());
        }
    }

    public function test_encrypted_credentials_are_hidden_from_model_serialization(): void
    {
        $account = new MailAccount();
        $account->setRawAttributes([
            'name' => 'Sales',
            'email' => 'sales@example.com',
            'imap_password_encrypted' => 'encrypted-imap-value',
            'smtp_password_encrypted' => 'encrypted-smtp-value',
        ]);

        $serialized = $account->toArray();

        $this->assertArrayNotHasKey('imap_password_encrypted', $serialized);
        $this->assertArrayNotHasKey('smtp_password_encrypted', $serialized);
    }
}
