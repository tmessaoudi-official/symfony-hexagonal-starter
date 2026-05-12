<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Model\Email;
use RuntimeException;

/**
 * Thrown when attempting to create a User with an email that is already registered.
 *
 * This is a domain invariant: email addresses must be unique.
 * The Infrastructure layer detects uniqueness (e.g. via a unique DB index) and
 * translates the storage-level error into this domain exception.
 */
final class UserAlreadyExistsException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function withEmail(Email $email): self
    {
        return new self(sprintf('A user with email "%s" already exists.', $email->value()));
    }
}
