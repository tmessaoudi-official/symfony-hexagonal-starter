<?php

declare(strict_types=1);

namespace App\Application\Command\PromoteUser;

final readonly class PromoteUserCommand
{
    public function __construct(
        public readonly string $userId,
        public readonly string $targetRole,
    ) {}
}
