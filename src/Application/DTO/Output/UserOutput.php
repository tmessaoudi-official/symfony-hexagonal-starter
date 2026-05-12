<?php

declare(strict_types=1);

namespace App\Application\DTO\Output;

use DateTimeImmutable;

/**
 * UserOutput — the read model DTO.
 *
 * WHY readonly?
 * - Read models are snapshots; they must not be mutated after construction.
 * - readonly enforces this at the language level — no accidental assignment.
 *
 * WHY strings instead of Value Objects?
 * - DTOs cross the Application → Infrastructure boundary.
 * - They are serialised to JSON by API Platform — primitives serialise without configuration.
 * - Value Objects are Domain concepts; the DTO layer doesn't need that richness.
 */
final readonly class UserOutput
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public string $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
