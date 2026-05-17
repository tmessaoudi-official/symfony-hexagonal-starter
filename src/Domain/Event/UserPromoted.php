<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Model\UserId;
use App\Domain\Model\UserRole;

final readonly class UserPromoted
{
    public function __construct(
        public readonly UserId   $userId,
        public readonly UserRole $previousRole,
        public readonly UserRole $newRole,
    ) {}
}
