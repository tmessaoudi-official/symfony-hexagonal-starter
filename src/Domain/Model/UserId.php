<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * UserId is an immutable Value Object wrapping a UUID string.
 *
 * WHY a VO instead of a plain string?
 * - Prevents UserId from being confused with any other UUID (type safety)
 * - Centralises format validation — callers can't accidentally pass a random string
 * - Makes domain method signatures self-documenting: getUserById(UserId $id) vs getUserById(string $id)
 */
final readonly class UserId implements \Stringable
{
    private string $value;

    private function __construct(string $value)
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid UUID.', $value));
        }

        $this->value = strtolower($value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Generate a new random UUID v4.
     * NOTE: This uses random_bytes — adequate for local generation.
     * In production you may inject a UlidGenerator or SymfonyUid.
     */
    public static function generate(): self
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80); // variant bits

        return new self(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)));
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
