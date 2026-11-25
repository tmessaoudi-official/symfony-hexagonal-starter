<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Model\Email;
use App\Domain\Model\User;
use App\Domain\Model\UserId;
use App\Domain\Model\UserRole;
use App\Domain\Repository\UserRepository;

/**
 * In-memory implementation of the Domain port UserRepository: zero framework, zero database.
 *
 * It proves the port can be satisfied by anything (Doctrine, Redis, an array in a test):
 * Domain code that depends on the interface neither knows nor cares which one it gets.
 *
 * It stores and returns snapshots (clones), like the Doctrine adapter, whose mapper builds a new
 * User on every read. Handing back the stored object itself would let a handler that changes a
 * user but forgets save() pass its tests: the change would already be "in the repository".
 * User holds only immutable values, enums and an array, so a shallow clone is a full snapshot.
 */
final class InMemoryUserRepository implements UserRepository
{
    /** @var list<User> */
    private array $users = [];

    /** @param list<User> $users */
    public function __construct(array $users = [])
    {
        foreach ($users as $user) {
            $this->save($user);
        }
    }

    /** Upsert, like the Doctrine adapter: saving a known user replaces it instead of adding a copy. */
    public function save(User $user): void
    {
        $this->delete($user);
        $this->users[] = clone $user;
    }

    public function getById(UserId $id): User
    {
        return $this->findById($id) ?? throw new \RuntimeException('Not found');
    }

    public function findById(UserId $id): ?User
    {
        foreach ($this->users as $user) {
            if ($user->id()->equals($id)) {
                return clone $user;
            }
        }

        return null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email()->equals($email)) {
                return clone $user;
            }
        }

        return null;
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->findByEmail($email) instanceof User;
    }

    /** @return list<User> */
    public function findAll(): array
    {
        return array_map(static fn (User $user): User => clone $user, $this->users);
    }

    /** @return list<User> */
    public function findPage(int $limit, ?UserId $cursor = null): array
    {
        return [];
    }

    public function delete(User $user): void
    {
        $this->users = array_values(array_filter($this->users, static fn (User $u): bool => !$u->id()->equals($user->id())));
    }

    public function countByRole(UserRole $role): int
    {
        return count(array_filter($this->users, static fn (User $u): bool => $u->role() === $role));
    }
}
