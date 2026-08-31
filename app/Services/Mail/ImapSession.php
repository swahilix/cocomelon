<?php

namespace App\Services\Mail;

use App\Exceptions\ImapException;

class ImapSession
{
    private int $tagCounter = 1;
    private bool $closed = false;

    public function __construct(private $stream)
    {
    }

    public function capabilities(): array
    {
        $response = $this->command('CAPABILITY');
        $capabilities = [];

        foreach ($response['untagged'] as $line) {
            if (preg_match('/^\*\s+CAPABILITY\s+(.+)$/i', $line, $matches)) {
                $capabilities = preg_split('/\s+/', trim($matches[1])) ?: [];
                break;
            }
        }

        return array_values(array_unique(array_map('strtoupper', $capabilities)));
    }

    public function listMailboxes(string $reference = '', string $pattern = '*'): array
    {
        $response = $this->command(sprintf(
            'LIST %s %s',
            $this->quote($reference),
            $this->quote($pattern),
        ));

        $mailboxes = [];
        foreach ($response['untagged'] as $line) {
            $parsed = $this->parseListLine($line);
            if ($parsed !== null) {
                $mailboxes[] = $parsed;
            }
        }

        return $mailboxes;
    }

    public function select(string $mailbox, bool $readOnly = false): array
    {
        $response = $this->command(($readOnly ? 'EXAMINE ' : 'SELECT ').$this->quote($mailbox));
        $result = [
            'exists' => null,
            'recent' => null,
            'uid_validity' => null,
            'uid_next' => null,
            'unseen' => null,
            'flags' => [],
            'permanent_flags' => [],
        ];

        foreach ($response['untagged'] as $line) {
            if (preg_match('/^\*\s+(\d+)\s+EXISTS$/i', $line, $m)) $result['exists'] = (int) $m[1];
            elseif (preg_match('/^\*\s+(\d+)\s+RECENT$/i', $line, $m)) $result['recent'] = (int) $m[1];
            elseif (preg_match('/\[UIDVALIDITY\s+(\d+)\]/i', $line, $m)) $result['uid_validity'] = (int) $m[1];
            elseif (preg_match('/\[UIDNEXT\s+(\d+)\]/i', $line, $m)) $result['uid_next'] = (int) $m[1];
            elseif (preg_match('/\[UNSEEN\s+(\d+)\]/i', $line, $m)) $result['unseen'] = (int) $m[1];
            elseif (preg_match('/^\*\s+FLAGS\s+\((.*)\)$/i', $line, $m)) $result['flags'] = $this->splitFlags($m[1]);
            elseif (preg_match('/\[PERMANENTFLAGS\s+\((.*)\)\]/i', $line, $m)) $result['permanent_flags'] = $this->splitFlags($m[1]);
        }

        return $result;
    }

    public function status(string $mailbox, array $items = ['MESSAGES', 'UNSEEN', 'UIDNEXT', 'UIDVALIDITY']): array
    {
        $allowed = ['MESSAGES', 'RECENT', 'UIDNEXT', 'UIDVALIDITY', 'UNSEEN'];
        $items = array_values(array_intersect($allowed, array_map('strtoupper', $items)));
        if ($items === []) {
            throw ImapException::failed('At least one valid STATUS item is required.');
        }

        $response = $this->command('STATUS '.$this->quote($mailbox).' ('.implode(' ', $items).')');
        foreach ($response['untagged'] as $line) {
            if (preg_match('/^\*\s+STATUS\s+.+\s+\((.*)\)$/i', $line, $m)) {
                $tokens = preg_split('/\s+/', trim($m[1])) ?: [];
                $result = [];
                for ($i = 0; $i + 1 < count($tokens); $i += 2) {
                    $result[strtolower($tokens[$i])] = ctype_digit($tokens[$i + 1]) ? (int) $tokens[$i + 1] : $tokens[$i + 1];
                }
                return $result;
            }
        }

        return [];
    }

    public function uidSearch(string $criteria = 'ALL'): array
    {
        $criteria = trim($criteria);
        if ($criteria === '' || str_contains($criteria, "\r") || str_contains($criteria, "\n")) {
            throw ImapException::failed('Invalid UID SEARCH criteria.');
        }

        $response = $this->command('UID SEARCH '.$criteria);
        foreach ($response['untagged'] as $line) {
            if (preg_match('/^\*\s+SEARCH(?:\s+(.*))?$/i', $line, $m)) {
                $value = trim($m[1] ?? '');
                if ($value === '') return [];
                return array_values(array_map('intval', preg_split('/\s+/', $value) ?: []));
            }
        }

        return [];
    }

    public function uidFetch(string $sequenceSet, string $dataItems): array
    {
        if (! preg_match('/^[0-9,:*]+$/', $sequenceSet)) {
            throw ImapException::failed('Invalid UID sequence set.');
        }
        $dataItems = trim($dataItems);
        if ($dataItems === '' || str_contains($dataItems, "\r") || str_contains($dataItems, "\n")) {
            throw ImapException::failed('Invalid UID FETCH data items.');
        }

        return $this->command("UID FETCH {$sequenceSet} {$dataItems}")['untagged'];
    }

    public function noop(): void
    {
        $this->command('NOOP');
    }

    public function logout(): void
    {
        if ($this->closed) return;

        try {
            $this->command('LOGOUT');
        } catch (ImapException) {
            // Best-effort cleanup. Connection is closed below regardless.
        }

        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->closed = true;
    }

    public function __destruct()
    {
        $this->logout();
    }

    public function command(string $command): array
    {
        if ($this->closed || ! is_resource($this->stream)) {
            throw ImapException::failed('The IMAP session is closed.');
        }
        if (str_contains($command, "\r") || str_contains($command, "\n")) {
            throw ImapException::failed('Invalid IMAP command.');
        }

        $tag = sprintf('A%04d', $this->tagCounter++);
        $written = @fwrite($this->stream, "{$tag} {$command}\r\n");
        if ($written === false) {
            throw ImapException::failed('Unable to write to the IMAP server.');
        }

        $untagged = [];
        $continuations = [];
        $tagged = null;

        for ($count = 0; $count < 10000; $count++) {
            $line = $this->readLine();
            if (str_starts_with(strtoupper($line), strtoupper($tag).' ')) {
                $tagged = $line;
                break;
            }
            if (str_starts_with($line, '+')) $continuations[] = $line;
            else $untagged[] = $line;
        }

        if ($tagged === null) {
            throw ImapException::failed('The IMAP server returned an incomplete response.');
        }
        if (! str_starts_with(strtoupper($tagged), strtoupper($tag).' OK')) {
            throw ImapException::failed($this->safeFailureMessage($tagged));
        }

        return ['tag' => $tag, 'tagged' => $tagged, 'untagged' => $untagged, 'continuations' => $continuations];
    }

    public function parseListLine(string $line): ?array
    {
        if (! preg_match('/^\*\s+LIST\s+\(([^)]*)\)\s+(NIL|"(?:[^"\\]|\\.)*")\s+(.+)$/i', $line, $m)) {
            return null;
        }

        $delimiter = strtoupper($m[2]) === 'NIL' ? null : $this->unquote($m[2]);
        $rawName = trim($m[3]);
        $name = str_starts_with($rawName, '"') ? $this->unquote($rawName) : $rawName;

        return [
            'name' => $name,
            'delimiter' => $delimiter,
            'flags' => $this->splitFlags($m[1]),
            'selectable' => ! str_contains(strtoupper($m[1]), '\\NOSELECT'),
        ];
    }

    private function readLine(): string
    {
        $line = fgets($this->stream, 65536);
        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);
            throw ImapException::failed(($meta['timed_out'] ?? false) ? 'Connection timed out.' : 'Server closed the connection unexpectedly.');
        }

        return rtrim($line, "\r\n");
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function unquote(string $value): string
    {
        $value = trim($value);
        if (strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
            $value = substr($value, 1, -1);
        }
        return preg_replace('/\\\\(.)/s', '$1', $value) ?? $value;
    }

    private function splitFlags(string $value): array
    {
        $value = trim($value);
        return $value === '' ? [] : (preg_split('/\s+/', $value) ?: []);
    }

    private function safeFailureMessage(string $tagged): string
    {
        $parts = preg_split('/\s+/', $tagged, 3) ?: [];
        $message = trim($parts[2] ?? 'Command was rejected by the server.');
        $message = preg_replace('/[\x00-\x1F\x7F]/', ' ', $message) ?? $message;
        return mb_substr($message, 0, 500);
    }
}
