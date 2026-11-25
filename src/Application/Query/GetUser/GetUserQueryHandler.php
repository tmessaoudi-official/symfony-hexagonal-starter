<?php

declare(strict_types=1);

namespace App\Application\Query\GetUser;

use App\Application\DTO\Output\UserOutput;
use App\Domain\Model\UserId;
use App\Domain\Repository\UserRepository;

/**
 * GetUserQueryHandler — fetches a single User and maps it to a read DTO.
 *
 * The handler delegates mapping to the domain object's public accessors.
 * No domain logic lives here — only orchestration.
 */
final readonly class GetUserQueryHandler
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function __invoke(GetUserQuery $query): UserOutput
    {
        $user = $this->users->getById(UserId::fromString($query->userId));

        return new UserOutput(
            id: $user->id()->value(),
            email: $user->email()->value(),
            name: $user->name()->value(),
            status: $user->status()->value,
            createdAt: $user->createdAt(),
            updatedAt: $user->updatedAt(),
        );
    }
}
