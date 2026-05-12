<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

/**
 * UserName Value Object.
 *
 * Encapsulates the constraints for a human-readable display name:
 * - Minimum 2 characters (single-letter names are almost always an error)
 * - Maximum 100 characters (sane UI/DB limit)
 * - No leading/trailing whitespace (stored normalised)
 */
final readonly class UserName
{
    private string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        if (strlen($trimmed) < 2) {
            throw new InvalidArgumentException('UserName must be at least 2 characters long.');
        }

        if (strlen($trimmed) > 100) {
            throw new InvalidArgumentException('UserName cannot exceed 100 characters.');
        }

        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
