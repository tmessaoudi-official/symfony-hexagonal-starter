<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Model\UserId;
use App\Domain\Model\UserRole;

final readonly class UserPromoted
{
    public function __construct(
        public UserId $userId,
        public UserRole $previousRole,
        public UserRole $newRole,
    ) {
    }
}
