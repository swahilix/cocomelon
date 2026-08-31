<?php

namespace App\Exceptions;

use Throwable;

class MailCredentialException extends MailConnectionException
{
    public static function missing(string $transport): self
    {
        return new self("No stored {$transport} mail credential is available.");
    }

    public static function invalid(string $transport, ?Throwable $previous = null): self
    {
        return new self(
            "The stored {$transport} mail credential cannot be decrypted. Verify the application encryption key and save the mailbox password again.",
            0,
            $previous,
        );
    }
}
