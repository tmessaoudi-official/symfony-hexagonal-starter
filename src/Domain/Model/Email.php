<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * Email Value Object.
 *
 * WHY validate here AND in the Input DTO?
 * - Input DTO validation catches user mistakes at the HTTP boundary (400 Bad Request).
 * - Domain validation is the last line of defence. The domain must protect its own invariants
 *   regardless of which adapter (HTTP, CLI, import job) calls it.
 * - Both layers can coexist cheaply — the domain check is a single regex call.
 */
final readonly class Email implements \Stringable
{
    private string $value;

    private function __construct(string $value)
    {
        $normalised = strtolower(trim($value));

        if ($normalised === '') {
            throw new \InvalidArgumentException('Email address cannot be empty.');
        }

        // Length first: FILTER_VALIDATE_EMAIL also rejects over-long parts, which would
        // otherwise hide this rule behind a generic "not a valid email" message.
        if (strlen($normalised) > 254) {
            throw new \InvalidArgumentException('Email address exceeds the maximum length of 254 characters.');
        }

        // FILTER_VALIDATE_EMAIL covers RFC 5321/5322 basics without a heavy library.
        // It rejects obvious misformat (no @, double dots, invalid TLD patterns).
        if (filter_var($normalised, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid email address.', $value));
        }

        $this->value = $normalised;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function domain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
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
