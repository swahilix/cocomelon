<?php

namespace Tests\Feature;

use App\Exceptions\MailConnectionException;
use App\Models\MailAccount;
use App\Models\User;
use App\Services\Mail\MailConnectionTester;
use App\Services\Mail\MailCredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MailConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_mailbox_imap_test_can_reuse_the_stored_encrypted_password(): void
    {
        $admin = $this->admin();
        $account = $this->mailAccount('saved-imap-secret', 'saved-smtp-secret');

        $tester = Mockery::mock(MailConnectionTester::class);
        $tester->shouldReceive('testImap')
            ->once()
            ->withArgs(fn (array $config): bool => $config['password'] === 'saved-imap-secret')
            ->andReturn(['ok' => true, 'message' => 'IMAP connection and authentication succeeded.']);
        $this->app->instance(MailConnectionTester::class, $tester);

        $this->actingAs($admin)->postJson('/admin/mail-settings/test-imap', [
            'account_id' => $account->id,
            'host' => 'mail.example.test',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'sales@example.test',
            'password' => null,
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertNotNull($account->fresh()->last_connection_at);
        $this->assertNull($account->fresh()->last_error);
    }

    public function test_new_mailbox_connection_test_requires_a_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/admin/mail-settings/test-smtp', [
            'account_id' => null,
            'host' => 'mail.example.test',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'sales@example.test',
            'password' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_connection_failure_returns_only_the_safe_error_message(): void
    {
        $admin = $this->admin();
        $account = $this->mailAccount('imap-secret', 'smtp-secret');

        $tester = Mockery::mock(MailConnectionTester::class);
        $tester->shouldReceive('testSmtp')
            ->once()
            ->andThrow(MailConnectionException::failed('SMTP', 'Authentication was rejected.'));
        $this->app->instance(MailConnectionTester::class, $tester);

        $response = $this->actingAs($admin)->postJson('/admin/mail-settings/test-smtp', [
            'account_id' => $account->id,
            'host' => 'mail.example.test',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'sales@example.test',
            'password' => 'temporary-secret-that-must-not-leak',
        ]);

        $response->assertUnprocessable()->assertJson([
            'ok' => false,
            'message' => 'SMTP connection test failed: Authentication was rejected.',
        ]);
        $this->assertStringNotContainsString('temporary-secret-that-must-not-leak', $response->getContent());
        $this->assertSame(
            'SMTP connection test failed: Authentication was rejected.',
            $account->fresh()->last_error,
        );
    }

    private function admin(): User
    {
        return User::query()->create([
            'name' => 'Administrator',
            'email' => 'mail-admin@example.test',
            'password' => 'Password123',
            'is_admin' => true,
        ]);
    }

    private function mailAccount(string $imapPassword, string $smtpPassword): MailAccount
    {
        $vault = app(MailCredentialVault::class);
        $account = new MailAccount();
        $account->forceFill([
            'name' => 'Sales',
            'email' => 'sales@example.test',
            'provider' => 'Custom',
            'imap_host' => 'mail.example.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'sales@example.test',
            'imap_password_encrypted' => $vault->encrypt($imapPassword),
            'smtp_host' => 'mail.example.test',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'sales@example.test',
            'smtp_password_encrypted' => $vault->encrypt($smtpPassword),
            'sync_enabled' => true,
            'send_enabled' => true,
            'status' => 'active',
        ]);
        $account->save();

        return $account;
    }
}
