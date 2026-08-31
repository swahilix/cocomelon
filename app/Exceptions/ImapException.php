<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ImapException extends RuntimeException
{
    public static function failed(string $message, ?Throwable $previous = null): self
    {
        return new self('IMAP error: '.$message, 0, $previous);
    }
}
