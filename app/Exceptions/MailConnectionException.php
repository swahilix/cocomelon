<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class MailConnectionException extends RuntimeException
{
    public static function failed(string $transport, string $reason, ?Throwable $previous = null): self
    {
        $safeReason = trim($reason) !== '' ? trim($reason) : 'Connection failed.';

        return new self("{$transport} connection test failed: {$safeReason}", previous: $previous);
    }
}
