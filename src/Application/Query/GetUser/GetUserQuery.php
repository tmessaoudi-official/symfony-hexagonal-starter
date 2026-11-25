<?php

declare(strict_types=1);

namespace App\Application\Query\GetUser;

/**
 * GetUserQuery — read intent for a single User by its ID.
 *
 * Queries are read-only: the handler must not change any state.
 * The handler returns a UserOutput DTO — never a domain entity.
 *
 * WHY not return the domain entity from a query?
 * - Domain entities carry behaviour; DTOs carry data. Leaking entities to the HTTP
 *   layer would couple the serialiser to the domain model.
 * - Read models can be optimised independently of write models (e.g. a denormalised
 *   view, a Redis cache, a search index) — DTOs make this transparent.
 */
final readonly class GetUserQuery
{
    public function __construct(
        public string $userId,
    ) {
    }
}
