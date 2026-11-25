<?php

declare(strict_types=1);

namespace App\Application\DTO\Output;

/**
 * Paginated list of Users with cursor metadata.
 *
 * @property list<UserOutput> $items
 */
final readonly class UserListOutput
{
    /**
     * @param list<UserOutput> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public bool $hasMore,
    ) {
    }
}
