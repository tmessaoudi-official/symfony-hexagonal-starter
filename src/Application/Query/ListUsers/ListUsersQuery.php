<?php

declare(strict_types=1);

namespace App\Application\Query\ListUsers;

/**
 * ListUsersQuery — cursor-based pagination query.
 *
 * WHY cursor pagination over offset?
 * - See docs/patterns/cursor-pagination.md for the full explanation.
 * - TL;DR: OFFSET n requires scanning n rows before returning results.
 *   With 1 million users, OFFSET 999980 LIMIT 20 scans 1 million rows.
 *   Cursor (WHERE id > :last_seen ORDER BY id LIMIT 20) always scans 20 rows.
 */
final readonly class ListUsersQuery
{
    public function __construct(
        public int $limit = 20,
        public ?string $afterCursor = null,
    ) {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Limit must be between 1 and 100.');
        }
    }
}
