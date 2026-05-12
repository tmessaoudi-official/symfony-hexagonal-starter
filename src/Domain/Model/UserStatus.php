<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * UserStatus Enum.
 *
 * WHY an enum over a string constant?
 * - PHP 8.4 backed enum: serialises to the string value for DB storage, never a raw magic string.
 * - Exhaustive match: the compiler tells you when you forget a case.
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
            self::Active   => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
