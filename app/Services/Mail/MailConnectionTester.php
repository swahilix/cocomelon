<?php

namespace App\Services\Mail;

use App\Exceptions\MailConnectionException;

class MailConnectionTester
{
    private const TIMEOUT_SECONDS = 8;

    public function testImap(array $config): array
    {
        $host = $this->safeHost((string) $config['host']);
        $port = (int) $config['port'];
        $encryption = strtolower((string) $config['encryption']);
        $username = (string) $config['username'];
        $password = (string) $config['password'];

        $stream = $this->connect($host, $port, $encryption === 'ssl', 'IMAP');

        try {
            $greeting = $this->readLine($stream, 'IMAP');
            if (! str_starts_with(strtoupper($greeting), '* OK')) {
                throw MailConnectionException::failed('IMAP', 'Server did not return a valid IMAP greeting.');
            }

            if (in_array($encryption, ['tls', 'starttls'], true)) {
                $this->write($stream, "A001 STARTTLS\r\n", 'IMAP');
                $response = $this->readImapTagged($stream, 'A001');
                if (! str_starts_with(strtoupper($response), 'A001 OK')) {
                    throw MailConnectionException::failed('IMAP', 'Server refused STARTTLS.');
                }
                $this->enableTls($stream, 'IMAP');
            }

            $quotedUser = $this->imapQuote($username);
            $quotedPassword = $this->imapQuote($password);
            $this->write($stream, "A002 LOGIN {$quotedUser} {$quotedPassword}\r\n", 'IMAP');
            $login = $this->readImapTagged($stream, 'A002');

            if (! str_starts_with(strtoupper($login), 'A002 OK')) {
                throw MailConnectionException::failed('IMAP', 'Authentication was rejected. Check the username, password, encryption and port.');
            }

            $this->write($stream, "A003 LOGOUT\r\n", 'IMAP', false);

            return [
                'ok' => true,
                'message' => 'IMAP connection and authentication succeeded.',
            ];
        } finally {
            fclose($stream);
        }
    }

    public function testSmtp(array $config): array
    {
        $host = $this->safeHost((string) $config['host']);
        $port = (int) $config['port'];
        $encryption = strtolower((string) $config['encryption']);
        $username = (string) $config['username'];
        $password = (string) $config['password'];

        $stream = $this->connect($host, $port, $encryption === 'ssl', 'SMTP');

        try {
            $greeting = $this->readSmtpResponse($stream);
            $this->expectSmtpCode($greeting, 220, 'Server did not return a valid SMTP greeting.');

            $this->write($stream, "EHLO prinx-mail.local\r\n", 'SMTP');
            $ehlo = $this->readSmtpResponse($stream);
            $this->expectSmtpCode($ehlo, 250, 'SMTP server rejected EHLO.');

            if (in_array($encryption, ['tls', 'starttls'], true)) {
                $this->write($stream, "STARTTLS\r\n", 'SMTP');
                $startTls = $this->readSmtpResponse($stream);
                $this->expectSmtpCode($startTls, 220, 'SMTP server refused STARTTLS.');
                $this->enableTls($stream, 'SMTP');

                $this->write($stream, "EHLO prinx-mail.local\r\n", 'SMTP');
                $ehlo = $this->readSmtpResponse($stream);
                $this->expectSmtpCode($ehlo, 250, 'SMTP server rejected EHLO after TLS negotiation.');
            }

            $this->write($stream, "AUTH LOGIN\r\n", 'SMTP');
            $auth = $this->readSmtpResponse($stream);
            $this->expectSmtpCode($auth, 334, 'SMTP server does not accept LOGIN authentication.');

            $this->write($stream, base64_encode($username)."\r\n", 'SMTP');
            $userPrompt = $this->readSmtpResponse($stream);
            $this->expectSmtpCode($userPrompt, 334, 'SMTP username was rejected.');

            $this->write($stream, base64_encode($password)."\r\n", 'SMTP');
            $passwordResponse = $this->readSmtpResponse($stream);
            $this->expectSmtpCode($passwordResponse, 235, 'Authentication was rejected. Check the username, password, encryption and port.');

            $this->write($stream, "QUIT\r\n", 'SMTP', false);

            return [
                'ok' => true,
                'message' => 'SMTP connection and authentication succeeded.',
            ];
        } finally {
            fclose($stream);
        }
    }

    private function connect(string $host, int $port, bool $implicitTls, string $transport)
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
            throw MailConnectionException::failed($transport, 'Unable to connect to the configured server.');
        }

        stream_set_timeout($stream, self::TIMEOUT_SECONDS);

        return $stream;
    }

    private function enableTls($stream, string $transport): void
    {
        $enabled = @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($enabled !== true) {
            throw MailConnectionException::failed($transport, 'TLS negotiation failed. Check the hostname and certificate.');
        }
    }

    private function readLine($stream, string $transport): string
    {
        $line = fgets($stream, 8192);
        if ($line === false) {
            $meta = stream_get_meta_data($stream);
            $reason = ($meta['timed_out'] ?? false) ? 'Connection timed out.' : 'Server closed the connection unexpectedly.';
            throw MailConnectionException::failed($transport, $reason);
        }

        return trim($line);
    }

    private function readImapTagged($stream, string $tag): string
    {
        for ($lines = 0; $lines < 100; $lines++) {
            $line = $this->readLine($stream, 'IMAP');
            if (str_starts_with(strtoupper($line), strtoupper($tag).' ')) {
                return $line;
            }
        }

        throw MailConnectionException::failed('IMAP', 'Server response was too large or incomplete.');
    }

    private function readSmtpResponse($stream): array
    {
        $lines = [];

        for ($count = 0; $count < 100; $count++) {
            $line = $this->readLine($stream, 'SMTP');
            $lines[] = $line;

            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        return $lines;
    }

    private function expectSmtpCode(array $response, int $expected, string $message): void
    {
        $last = end($response) ?: '';
        $code = (int) substr($last, 0, 3);

        if ($code !== $expected) {
            throw MailConnectionException::failed('SMTP', $message);
        }
    }

    private function write($stream, string $payload, string $transport, bool $throw = true): void
    {
        $written = @fwrite($stream, $payload);
        if ($written === false && $throw) {
            throw MailConnectionException::failed($transport, 'Unable to write to the mail server.');
        }
    }

    private function imapQuote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function safeHost(string $host): string
    {
        $host = trim($host);

        if ($host === '' || strlen($host) > 253 || str_contains($host, '://') || str_contains($host, '/') || str_contains($host, '\\')) {
            throw MailConnectionException::failed('Mail', 'Invalid server hostname.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $host;
        }

        if (! preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/', $host)) {
            throw MailConnectionException::failed('Mail', 'Invalid server hostname.');
        }

        return $host;
    }
}
