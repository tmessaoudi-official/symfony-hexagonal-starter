<?php

declare(strict_types=1);

namespace App\Application\Command\CreateUser;

/**
 * CreateUserCommand — a write intent with validated primitive inputs.
 *
 * WHY primitives, not Value Objects?
 * - Commands cross layer boundaries (HTTP, CLI, Messenger).
 * - They must be serialisable without domain knowledge.
 * - The CommandHandler constructs VOs and enforces domain invariants.
 *
 * WHY readonly?
 * - Commands are immutable data carriers — they represent a decision that was already made.
 * - Mutation after creation would be a bug, not a feature.
 */
final readonly class CreateUserCommand
{
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}
