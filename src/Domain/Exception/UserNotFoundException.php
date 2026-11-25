<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Model\UserId;

/**
 * Thrown when a User aggregate cannot be found by its identifier.
 *
 * WHY a domain exception instead of relying on Doctrine NoResultException?
 * - Doctrine is Infrastructure. The domain must not leak its storage mechanism.
 * - The Infrastructure repository catches Doctrine exceptions and re-throws this one.
 * - HTTP adapters catch THIS exception and decide what HTTP status to return (404).
 *   The domain doesn't know about HTTP.
 */
final class UserNotFoundException extends \RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function withId(UserId $id): self
    {
        return new self(sprintf('User with id "%s" was not found.', $id->value()));
    }

    public static function withEmail(string $email): self
    {
        return new self(sprintf('User with email "%s" was not found.', $email));
    }
}
