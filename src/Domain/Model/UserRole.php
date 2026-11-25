<?php

declare(strict_types=1);

namespace App\Domain\Model;

enum UserRole: string
{
    case Regular = 'regular';
    case Moderator = 'moderator';
    case Admin = 'admin';

    /**
     * Returns true if $this is the immediate next role above $from.
     * Prevents skipping levels (Regular→Admin) and demotion (Admin→Regular).
     */
    public function isDirectPromotionOf(self $from): bool
    {
        return match ($this) {
            self::Moderator => $from === self::Regular,
            self::Admin => $from === self::Moderator,
            self::Regular => false,
        };
    }
}
