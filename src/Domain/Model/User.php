<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\UserCreated;
use App\Domain\Event\UserPromoted;

/**
 * User Aggregate Root.
 *
 * WHY an Aggregate and not just an entity?
 * - An Aggregate is a cluster of domain objects treated as a unit for data changes.
 * - User is simple enough to be its own aggregate boundary: it owns its identity,
 *   email, and name. Nothing else is nested here yet.
 * - All changes go through the aggregate — no direct property mutation from outside.
 *
 * WHY NOT Doctrine annotations here?
 * - Domain entities must be pure PHP. Infrastructure\Persistence\Doctrine\Entity\User
 *   is the Doctrine-aware counterpart. A UserMapper bridges the two.
 * - This separation means you can swap Doctrine for any other ORM without touching
 *   the domain — the core business logic never changes.
 *
 * WHY record events inside the aggregate?
 * - Domain events are a side-effect of domain state changes.
 * - The aggregate records them; the CommandHandler collects and dispatches them.
 * - This keeps the aggregate in full control of WHAT events it produces.
 */
final class User
{
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        private readonly UserId $id,
        private Email $email,
        private UserName $name,
        private UserStatus $status,
        private UserRole $role,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        UserId $id,
        Email $email,
        UserName $name,
        UserRole $role = UserRole::Regular,
    ): self {
        $now = new \DateTimeImmutable();

        $user = new self(
            id: $id,
            email: $email,
            name: $name,
            status: UserStatus::Active,
            role: $role,
            createdAt: $now,
            updatedAt: $now,
        );

        $user->record(new UserCreated($id, $email, $name));

        return $user;
    }

    /**
     * Reconstitute a User from persistence without raising events.
     *
     * WHY a separate factory method?
     * - Loading from the DB should NOT fire UserCreated again.
     * - Named constructors make the distinction explicit — no silent bugs.
     */
    public static function reconstitute(
        UserId $id,
        Email $email,
        UserName $name,
        UserStatus $status,
        UserRole $role,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            email: $email,
            name: $name,
            status: $status,
            role: $role,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function rename(UserName $name): void
    {
        $this->name = $name;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function changeEmail(Email $email): void
    {
        $this->email = $email;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->status = UserStatus::Inactive;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->status = UserStatus::Active;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function promote(UserRole $newRole): void
    {
        $previousRole = $this->role;
        $this->role = $newRole;
        $this->updatedAt = new \DateTimeImmutable();

        $this->record(new UserPromoted($this->id, $previousRole, $newRole));
    }

    // ─── Accessors ───────────────────────────────────────────────────────────

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function name(): UserName
    {
        return $this->name;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function role(): UserRole
    {
        return $this->role;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // ─── Domain Events ───────────────────────────────────────────────────────

    private function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Pull recorded events and clear the internal queue.
     *
     * @return list<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
