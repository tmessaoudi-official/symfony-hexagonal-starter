<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserRole;

/**
 * UserRepository Port (outbound).
 *
 * WHY an interface in the Domain layer?
 * - The Domain defines WHAT it needs (a place to persist and retrieve Users).
 * - The Infrastructure provides HOW (Doctrine, Redis, an in-memory fake for tests).
 * - The domain never imports an Infrastructure class — this is the Dependency Inversion Principle.
 *
 * WHY not return nullable from findById?
 * - Callers that want to handle "not found" gracefully call findById.
 * - Callers that require the User to exist call getById (throws UserNotFoundException).
 * - Splitting the intent prevents null checks scattered across application code.
 */
interface UserRepository
{
    public function save(User $user): void;

    public function getById(UserId $id): User;

    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function existsByEmail(Email $email): bool;

    /**
     * @return list<User>
     */
    public function findAll(): array;

    /**
     * Cursor-based page — returns at most $limit users whose id > $cursor.
     * Cursor is the last seen UserId (null = start from beginning).
     *
     * @return list<User>
     */
    public function findPage(int $limit, ?UserId $cursor = null): array;

    public function delete(User $user): void;

    public function countByRole(UserRole $role): int;
}
