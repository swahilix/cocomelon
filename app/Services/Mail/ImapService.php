<?php

namespace App\Services\Mail;

use App\Exceptions\ImapException;
use App\Models\MailAccount;

class ImapService
{
    private const TIMEOUT_SECONDS = 15;

    public function __construct(private readonly MailCredentialVault $credentials)
    {
    }

    public function connect(MailAccount $account): ImapSession
    {
        $host = $this->safeHost((string) $account->imap_host);
        $port = (int) $account->imap_port;
        $encryption = strtolower((string) $account->imap_encryption);

        if ($port < 1 || $port > 65535) {
            throw ImapException::failed('Invalid IMAP port.');
        }
        if (! in_array($encryption, ['ssl', 'tls', 'starttls', 'none'], true)) {
            throw ImapException::failed('Unsupported IMAP encryption mode.');
        }

        $stream = $this->openStream($host, $port, $encryption === 'ssl');

        try {
            $greeting = $this->readLine($stream);
            if (! str_starts_with(strtoupper($greeting), '* OK')) {
                throw ImapException::failed('Server did not return a valid greeting.');
            }

            if (in_array($encryption, ['tls', 'starttls'], true)) {
                $this->write($stream, "A0001 STARTTLS\r\n");
                $response = $this->readTagged($stream, 'A0001');
                if (! str_starts_with(strtoupper($response), 'A0001 OK')) {
                    throw ImapException::failed('Server refused STARTTLS.');
                }
                $this->enableTls($stream);
            }

            $username = $this->quote((string) $account->imap_username);
            $password = $this->quote($this->credentials->imapPassword($account));
            $this->write($stream, "A0002 LOGIN {$username} {$password}\r\n");
            $login = $this->readTagged($stream, 'A0002');

            if (! str_starts_with(strtoupper($login), 'A0002 OK')) {
                throw ImapException::failed('Authentication was rejected. Check the IMAP username, password, encryption and port.');
            }

            return new ImapSession($stream);
        } catch (\Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if ($exception instanceof ImapException) {
                throw $exception;
            }
            throw ImapException::failed('Unable to establish the IMAP session.', $exception);
        }
    }

    public function withSession(MailAccount $account, callable $callback): mixed
    {
        $session = $this->connect($account);

        try {
            return $callback($session);
        } finally {
            $session->logout();
        }
    }

    private function openStream(string $host, int $port, bool $implicitTls)
    {
        $scheme = $implicitTls ? 'ssl' : 'tcp';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);

        $errno = 0;
        $error = '';
        $stream = @stream_socket_client(
            "{$scheme}://{$host}:{$port}",
            $errno,
            $error,
            self::TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (! is_resource($stream)) {
            throw ImapException::failed('Unable to connect to the configured IMAP server.');
        }

        stream_set_timeout($stream, self::TIMEOUT_SECONDS);

        return $stream;
    }

    private function enableTls($stream): void
    {
        $enabled = @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($enabled !== true) {
            throw ImapException::failed('TLS negotiation failed. Check the IMAP hostname and certificate.');
        }
    }

    private function readLine($stream): string
    {
        $line = fgets($stream, 65536);
        if ($line === false) {
            $meta = stream_get_meta_data($stream);
            throw ImapException::failed(($meta['timed_out'] ?? false) ? 'Connection timed out.' : 'Server closed the connection unexpectedly.');
        }

        return rtrim($line, "\r\n");
    }

    private function readTagged($stream, string $tag): string
    {
        for ($count = 0; $count < 1000; $count++) {
            $line = $this->readLine($stream);
            if (str_starts_with(strtoupper($line), strtoupper($tag).' ')) {
                return $line;
            }
        }

        throw ImapException::failed('Server response was incomplete.');
    }

    private function write($stream, string $payload): void
    {
        if (@fwrite($stream, $payload) === false) {
            throw ImapException::failed('Unable to write to the IMAP server.');
        }
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function safeHost(string $host): string
    {
        $host = trim($host);
        if ($host === '' || strlen($host) > 253 || str_contains($host, '://') || str_contains($host, '/') || str_contains($host, '\\')) {
            throw ImapException::failed('Invalid IMAP hostname.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $host;
        }

        if (! preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/', $host)) {
            throw ImapException::failed('Invalid IMAP hostname.');
        }

        return $host;
    }
}
