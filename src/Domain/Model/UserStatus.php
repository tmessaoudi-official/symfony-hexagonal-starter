<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * UserStatus Enum.
 *
 * WHY an enum over a string constant?
 * - Backed enum (PHP 8.1+): stored as its string value, never a raw magic string in the code.
 * - Exhaustive match: PHPStan reports a match that forgets a case; at runtime PHP throws
 *   UnhandledMatchError instead of silently falling through.
 * - Self-documenting: User::$status is a UserStatus, not an opaque string.
 *
 * WHY not more statuses?
 * - YAGNI. Add SUSPENDED, LOCKED, BANNED when business requirements exist for them.
 *   Adding them now would require handlers, events, and policy logic that don't exist yet.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
