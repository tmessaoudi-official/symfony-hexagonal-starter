<?php

declare(strict_types=1);

namespace App\Application\Query\ListUsers;

use App\Application\DTO\Output\UserListOutput;
use App\Application\DTO\Output\UserOutput;
use App\Domain\Model\UserId;
use App\Domain\Repository\UserRepository;

/**
 * ListUsersQueryHandler — returns a cursor-paginated list of UserOutput DTOs.
 *
 * Fetches limit + 1 items from the repository:
 * - If we get limit + 1 back, there is a next page (hasMore = true); drop the last item.
 * - The nextCursor is the ID of the last returned item (not the +1 sentinel).
 */
final class ListUsersQueryHandler
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    public function __invoke(ListUsersQuery $query): UserListOutput
    {
        $cursor = $query->afterCursor !== null
            ? UserId::fromString($query->afterCursor)
            : null;

        // Fetch one extra item to determine if there is a next page.
        $users = $this->users->findPage($query->limit + 1, $cursor);

        $hasMore = count($users) > $query->limit;
        if ($hasMore) {
            array_pop($users);
        }

        $items = array_map(
            fn ($user) => new UserOutput(
                id: $user->id()->value(),
                email: $user->email()->value(),
                name: $user->name()->value(),
                status: $user->status()->value,
                createdAt: $user->createdAt(),
                updatedAt: $user->updatedAt(),
            ),
            $users,
        );

        $nextCursor = $hasMore && !empty($items)
            ? $items[array_key_last($items)]->id
            : null;

        return new UserListOutput(
            items: $items,
            nextCursor: $nextCursor,
            hasMore: $hasMore,
        );
    }
}
