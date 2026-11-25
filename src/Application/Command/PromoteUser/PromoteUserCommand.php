<?php

declare(strict_types=1);

namespace App\Application\Command\PromoteUser;

final readonly class PromoteUserCommand
{
    public function __construct(
        public string $userId,
        public string $targetRole,
    ) {
    }
}
